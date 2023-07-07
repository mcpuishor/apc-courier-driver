<?php
declare(strict_types=1);
namespace Mcpuishor\ApcCourierDriver\DataObjects;

use Mcpuishor\ApcCourierDriver\DataObjects\Address;

class Consignment extends \Mcpuishor\CourierManager\DataObjects\Consignment
{
    public static function fromResponse(\stdClass $consignment) : self
    {
        return new static([
           'waybill' => $consignment->WayBill,
           'reference' => $consignment->Reference,
           'service' => $consignment->ProductCode,
           'to' => Address::fromResponse($consignment->Delivery),
           'from' => Address::fromResponse($consignment->Collection),
           'deliveryInstructions' => $consignment->Delivery->Instructions,
           'packages' => collect(
               (array)$consignment->ShipmentDetails->Items
           ),
        ]);
    }
}
