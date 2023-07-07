<?php
declare(strict_types=1);
namespace Mcpuishor\ApcCourierDriver;

use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Mcpuishor\ApcCourierDriver\DataObjects\Service;
use Mcpuishor\ApcCourierDriver\Enums\ApcCourierStatus;
use Mcpuishor\CourierManager\Contracts\Courier;
use Mcpuishor\CourierManager\DataObjects\{Booking, Label};
use Mcpuishor\ApcCourierDriver\DataObjects\Consignment;
use Mcpuishor\CourierManager\Enums\LabelFileFormat;
use Illuminate\Support\Facades\Http;
use Mcpuishor\CourierManager\Exceptions\BookingErrorException;
use Mcpuishor\CourierManager\Exceptions\ConsignmentNotFoundException;
use Mcpuishor\CourierManager\Exceptions\EndpointNotAvailableException;
use Mcpuishor\CourierManager\Exceptions\NoServicesException;
use Mcpuishor\CourierManager\Exceptions\UnknwonClientErrorException;

class ApcCourier implements Courier
{
    /**
     * @throws BookingErrorException
     */
    public function book(Booking $booking): Consignment
    {
        $request = [
            "Orders" => [
                "Order" => [
                    "CollectionDate" => $booking->date->format('d/m/Y'),
                    "ReadyAt" => config('apc-courier-driver.businessday.ready'),
                    "ClosedAt" => config('apc-courier-driver.businessday.close'),
                    "ProductCode" => $booking->service,
                    "Collection" => [
                        "CompanyName" => $booking->from->company,
                        "AddressLine1" => $booking->from->line1,
                        "AddressLine2" => $booking->from->line2,
                        "PostalCode" => $booking->from->postcode,
                        "City" => $booking->from->city,
                        "County" => $booking->from->county,
                        "CountryCode" => $booking->from->country,
                        "Contact" => [
                            "PersonName" => $booking->from->contact->name,
                            "PhoneNumber" => $booking->from->contact->phone,
                            "Email" => $booking->from->contact->email,
                        ]
                    ],
                    "Delivery" => [
                        "CompanyName" => $booking->to->company,
                        "AddressLine1" => $booking->to->line1,
                        "AddressLine2" => $booking->to->line2,
                        "PostalCode" => $booking->to->postcode,
                        "City" => $booking->to->city,
                        "County" => $booking->to->county,
                        "CountryCode" => $booking->to->country,
                        "Contact" => [
                            "PersonName" => $booking->to->contact->name,
                            "PhoneNumber" => $booking->to->contact->phone,
                            "Email" => $booking->to->contact->email,
                        ],
                        "Instructions" =>$booking->deliveryInstructions,
                    ],
                    "GoodsInfo" => [
                        "GoodsValue" => 1,
                        "GoodsDescription" => "",
                        "Fragile" => true,
                        "Security"=> false,
                        "IncreasedLiability" => false,
                    ],
                    "ShipmentDetails" => [
                        "NumberOfPieces" => $booking->parcels->count(),
                        "Items" => [
                            "Item" => $booking->parcels
                        ],
                    ]
                ]
            ]
        ];

        $response = $this->getMockedBookedResponse();

//        $response = $this->prepareRequest()
//                    ->post(
//                        url:$this->getEndpoint('Orders') . config('apc-courier-driver.endpoints.book'),
//                        data: $request
//                    );
//
//        $this->validateHttpResponse($response);

        if ($response->Orders->Messages->Code == "SUCCESS") {
            $consignment = $response->Orders->Order;
            $consignment = Consignment::fromResponse($consignment);
        } else {
            throw new BookingErrorException('Could not book consignment.');
        }

        return $consignment;
    }

    public function name(): string
    {
        return 'apc-courier-driver';
    }

    /**
     * @throws EndpointNotAvailableException
     * @throws ConsignmentNotFoundException
     */
    public function isEndpointAvailable(): bool
    {
       $response =  $this->prepareRequest()
                    ->get(
                        $this->getEndpoint('')
                    );

       $this->validateHttpResponse($response);

       return $response->status() == 200;
    }

    /**
     * @throws EndpointNotAvailableException
     * @throws ConsignmentNotFoundException
     * @throws NoServicesException
     */
    public function isServiceAvailable(Booking $booking): bool
    {
        $services = $this->getServicesForBooking($booking);

        if (!$services->first(fn($s) => $s->ProductCode == $booking->service)) {
            throw new NoServicesException('No services available for the booking.');
        }

       return true;
    }

    /**
     * @throws EndpointNotAvailableException
     * @throws ConsignmentNotFoundException
     * @throws NoServicesException
     */
    public function getServicesForBooking(Booking $booking): Collection
    {
        $request = [
            "Orders" => [
                "Order" => [
                    "Reference" => $booking->reference,
                    "CollectionDate" => $booking->date->format('d/m/Y'),
                    "ReadyAt" => config('apc-courier-driver.businessday.ready'),
                    "ClosedAt" => config('apc-courier-driver.businessday.close'),
                    "Collection" => [
                        "PostalCode" => $booking->from->postcode,
                        "CountryCode" => $booking->from->country,
                    ],
                    "Delivery" => [
                        "PostalCode" => $booking->to->postcode,
                        "CountryCode" => $booking->to->country,
                    ],
                    "GoodsInfo" => [
                        "GoodsValue" => 1,
                        "Fragile" => true,
                    ],
                    "ShipmentDetails" => [
                        "NumberofPieces" => $booking->parcels->count(),
                        "Items" => ["Item" => $booking->parcels],
                    ]
                ]
            ]
        ];

        $response = $this->prepareRequest()
                        ->post(
                            url:$this->getEndpoint('services'),
                            data: $request
                        );

        $this->validateHttpResponse($response);
        //$response = $this->mockServicesResponse();

        $services = collect();

        if (!isset($response->ServiceAvailability->Services->Service)) {
            throw new NoServicesException('No services available for the booking.');
        }

        foreach($response->ServiceAvailability->Services->Service as $service) {
             $services->push(new Service((array) $service));
        }

        return $services;
    }

    /**
     * @throws EndpointNotAvailableException
     * @throws ConsignmentNotFoundException
     */
    public function label(string $consignmentNumber, LabelFileFormat $format): Label
    {
        $response = $this->prepareRequest()
                        ->get(
                            url:$this->getEndpointWithWaybill($consignmentNumber, "label")
                        );

        $this->validateHttpResponse($response);


        //TODO parse response and extract label in a Label DataObject
        // throw exceptions if call is unsuccessful or if no label is available

        return new Label();
    }

    /**
     * @throws EndpointNotAvailableException
     * @throws ConsignmentNotFoundException
     */
    public function traces(string $consignmentNumber): Collection
    {
        $response = $this->prepareRequest()
                    ->get(
                        url: $this->getEndpointWithWaybill($consignmentNumber, "tracks")
                    );

        $this->validateHttpResponse($response);

        if (isset($response->json()['Tracks']['Messages']['Code']) && $response->json()['Tracks']['Messages']['Code'] == 108) {
            throw new ConsignmentNotFoundException();
        }

        return isset($response->json()["Tracks"])
               ? collect($response->json()["Tracks"])
               : collect();
    }

    /**
     * @throws EndpointNotAvailableException
     * @throws ConsignmentNotFoundException
     */
    public function cancel(string $consignmentNumber): bool
    {
        $request = [
            "CancelOrder" => [
                "Order" => [
                    "Status" => "CANCELLED",
                ],
            ],
        ];

        $response = $this->prepareRequest()
                    ->put(
                      url: $this->getEndpointWithWaybill($consignmentNumber, 'cancel'),
                      data: $request
                    );

        $this->validateHttpResponse($response);

        return isset($response->json()['CancelOrder']['Messages']['Code'])
                && $response->json()['CancelOrder']['Messages']['Code'] === ApcCourierStatus::CANCELLED->value;
    }

    public function authentication(): string
    {
        return base64_encode(
            config('apc-courier-driver.username')
            . ":" .
            config('apc-courier-driver.password')
        );
    }

    public function prepareRequest() : PendingRequest
    {
        return Http::withHeaders([
                'remote-user' => 'Basic ' . $this->authentication(),
                'Content-Type' => config('apc-courier-driver.content'),
            ])
            ->accept(config('apc-courier-driver.content'));
    }

    private function getEndpoint(string $endpoint) : string
    {
        return config('apc-courier-driver.endpoints.' . config('apc-courier-driver.mode') )
                . config('apc-courier-driver.endpoints.' . $endpoint);
    }

    private function getEndpointWithWaybill(string $waybill, string $endpoint): string
    {
        return config('apc-courier-driver.endpoints.' . config('apc-courier-driver.mode') )
            . Str::replace('{waybill}', $waybill, config('apc-courier-driver.endpoints.' . $endpoint));
    }

    /**
     * @throws EndpointNotAvailableException
     * @throws ConsignmentNotFoundException
     */
    private function validateHttpResponse(Response $response) : void
    {
        switch ($response->status()) {
            case 404:
                throw new ConsignmentNotFoundException('APC Hypaship - Endpoint not found error.');
                break;
            case 419:
                throw new UnknwonClientErrorException('APC Hypaship - Unknown client error.');
                break;
            case 503:
                throw new EndpointNotAvailableException('APC Hypaship - Service temporarily unavailable.');
                break;
        }

    }

    private function getMockedBookedResponse(): \stdClass
    {
        return json_decode("{
 \"Orders\": {
 \"AccountNumber\": null,
 \"Messages\": {
 \"Code\": \"SUCCESS\",
 \"Description\": \"SUCCESS\"
 },
 \"Order\": {
 \"Messages\": {
 \"Code\": \"SUCCESS\",
 \"Description\": \"SUCCESS\"
 },
 \"AccountNumber\": [
 \"ANC001\",
 \"ANC001\"
 ],
 \"EntryType\": \"API\",
 \"CollectionDate\": \"19/04/2018\",
 \"ReadyAt\": \"18:00\",
 \"ClosedAt\": \"18:30\",
 \"ProductCode\": \"ND16\",
 \"RuleName\": null,
 \"ItemOption\": \"Weight\",
 \"OrderNumber\": \"000000000149567219\",
 \"WayBill\": \"2018041910099660000599\",
 \"Reference\": \"TEST\",
 \"CustomReference1\": null,
 \"CustomReference2\": null,
 \"CustomReference3\": null,
 \"AdultSignature\": null,
 \"Depots\": {
 \"RequestDepot\": \"100\",
 \"CollectingDepot\": \"44\",
 \"DeliveryDepot\": \"53\",
 \"Route\": \"APC\",
 \"IsScottish\": \"true\",
 \"Zone\": \"Z\",
 \"Presort\": null
 },
 \"Collection\": {
 \"CompanyName\": \"APC API and Co\",
 \"AddressLine1\": \"National Sortation Centre\",
 \"AddressLine2\": \"Kingswood Lakeside\",
 \"PostalCode\": \"WS11 8LD\",
 \"City\": \"Cannock\",
 \"County\": \"Staffordshire\",
 \"CountryCode\": \"GB\",
 \"CountryName\": \"United Kingdom\",
 \"Contact\": {
 \"PersonName\": \"Fred Smith\",
 \"PhoneNumber\": \"01922702580\",
 \"Email\": null
 },
 \"Instructions\": null
 },
 \"Delivery\": {
 \"CompanyName\": \"The Big Company Ltd\",
 \"AddressLine1\": \"Big Company House\",
 \"AddressLine2\": \"177 Big Street\",
 \"PostalCode\": \"M17 1WA\",
 \"City\": \"Sale\",
 \"County\": \"Cheshire\",
 \"CountryCode\": \"GB\",
 \"CountryName\": \"United Kingdom\",
 \"Contact\": {
 \"PersonName\": \"Jack Fox\",
 \"PhoneNumber\": \"08000280000\",
 \"MobileNumber\": null,
 \"Email\": \"api_support@apc-overnight.com\"
 },
 \"Instructions\": \"leave with neighbour\"
 },
 \"GoodsInfo\": {
 \"GoodsValue\": \"200\",
 \"GoodsDescription\": \".....\",
 \"PremiumInsurance\": \"false\",
 \"Fragile\": \"false\",
 \"Security\": \"false\",
 \"IncreasedLiability\": \"false\",
 \"Premium\": \"false\",
 \"NonConv\": \"false\"
 },
 \"ShipmentDetails\": {
 \"NumberOfPieces\": \"1\",
 \"TotalWeight\": \"1\",
 \"VolumetricWeight\": \"1.96\",
 \"Items\": {
 \"Item\": {
 \"ItemNumber\": \"000000000149567219\",
 \"TrackingNumber\": \"2018041910099660000599001\",
 \"Type\": \"PARCEL\",
 \"Weight\": \"1.000\",
 \"Length\": \"32.000\",
 \"Width\": \"23.000\",
 \"Height\": \"16.000\",
 \"Value\": \"200\",
 \"Reference\": \"PartA\"
 }
 }
 },
 \"Rates\": {
 \"Rate\": \"0.00\",
 \"ExtraCharges\": \"0.00\",
 \"FuelCharge\": \"0.00\",
 \"InsuranceCharge\": \"0.00\",
 \"Vat\": \"0.00\",
 \"TotalCost\": \"0.00\",
 \"Currency\": \"GBP\"
 }
 }
 }
}");
    }

    private function mockServicesResponse(): \stdClass
    {

        return json_decode("{\"ServiceAvailability\": {
        \"AccountNumber\": null,
 \"Messages\": {
            \"Code\": \"SUCCESS\",
 \"Description\": \"SUCCESS\"
 },
 \"Order\": {
            \"Collection\": {
                \"PostalCode\": \"WS11 8LD\",
\"CountryCode\": \"GB\"
 },
 \"Delivery\": {
                \"PostalCode\": \"M17 1WA\",
\"CountryCode\": \"GB\"
 }
 },
 \"Services\": {
            \"Service\": [
 {
     \"Carrier\": \"APC OVERNIGHT\",
\"ServiceName\": \"0900 COURIER PACK\",
\"ProductCode\": \"APCCP09\",
 \"MinTransitDays\": \"1\",
\"MaxTransitDays\": \"1\",
\"Tracked\": \"false\",
\"Signed\": \"false\",
\"MaxCompensation\": \"15000.00\",
\"MaxItemLength\": \"60\",
\"MaxItemWidth\": \"45\",
\"MaxItemHeight\": \"45\",
\"ItemType\": \"PACK\",
 \"DeliveryGroup\": null,
\"CollectionDate\": \"04/04/2018\",
\"EstimatedDeliveryDate\": \"05/04/2018\",
\"LatestBookingDateTime\": \"04/04/2018 13:50\",
\"Rate\": \"0.00\",
\"ExtraCharges\": \"0.00\",
\"FuelCharge\": \"0.00\",
\"InsuranceCharge\": \"0.00\",
\"Vat\": \"0.00\",
\"TotalCost\": \"0.00\",
\"Currency\": \"GBP\",
\"VolumetricWeight\": \"0.00\",
\"WeightUnit\": \"KG\"
 },
 {
     \"Carrier\": \"APC OVERNIGHT\",
\"ServiceName\": \"0110 COURIER PACK\",
\"ProductCode\": \"APCCP09\",
 \"MinTransitDays\": \"1\",
\"MaxTransitDays\": \"1\",
\"Tracked\": \"false\",
\"Signed\": \"false\",
\"MaxCompensation\": \"15000.00\",
\"MaxItemLength\": \"60\",
\"MaxItemWidth\": \"45\",
\"MaxItemHeight\": \"45\",
\"ItemType\": \"PACK\",
 \"DeliveryGroup\": null,
\"CollectionDate\": \"04/04/2018\",
\"EstimatedDeliveryDate\": \"05/04/2018\",
\"LatestBookingDateTime\": \"04/04/2018 13:50\",
\"Rate\": \"0.00\",
\"ExtraCharges\": \"0.00\",
\"FuelCharge\": \"0.00\",
\"InsuranceCharge\": \"0.00\",
\"Vat\": \"0.00\",
\"TotalCost\": \"0.00\",
\"Currency\": \"GBP\",
\"VolumetricWeight\": \"0.00\",
\"WeightUnit\": \"KG\"
 }

 ]
 }
}
}");
    }

}
