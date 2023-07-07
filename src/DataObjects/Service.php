<?php
declare(strict_types=1);
namespace Mcpuishor\ApcCourierDriver\DataObjects;

use Illuminate\Support\Carbon;

class Service extends \Mcpuishor\CourierManager\DataObjects\DataTransferObject
{
    public string $Carrier;
    public string $ServiceName;
    public string $ProductCode;
    public int $MinTransitDays;
    public int $MaxTransitDays;
    public bool $Tracked;
    public bool $Signed;
    public int $MaxCompensation;
    public int $MaxItemLength;
    public int $MaxItemHeight;
    public int $MaxItemWidth;
    public string $ItemType;
    public string $DeliveryGroup;
    public Carbon $CollectionDate;
    public Carbon $EstimatedDeliveryDate;
    public Carbon $LatestBookingDateTime;
    public float $Rate;
    public float $ExtraCharges;
    public float $FuelCharge;
    public float $InsuranceCharge;
    public float $Vat;
    public float $TotalCost;
    public string $Currency;
    public int $VolumetricWeight;
    public string $WeightUnit;

    public function rules(): array
    {
        return [
            'Carrier' => ['string'],
            'ServiceName' => ['string', 'required'],
            'ProductCode' => ['string', 'required'],
            'MinTransitDays' => ['numeric', 'gte:0'],
            'MaxTransitDays' => ['numeric', 'gte:0'],
            'Tracked' => ['bool', 'required'],
            'Signed' => ['bool', 'sometimes'],
            'MaxCompensation' => ['numeric', 'gte:0'],
            'MaxItemLength' => ['numeric', 'gte:0'],
            'MaxItemHeight' => ['numeric', 'gte:0'],
            'MaxItemWidth' => ['numeric', 'gte:0'],
            'ItemType' => ['string'],
            'DeliveryGroup' => ['string'],
            'CollectionDate' => ['date'],
            'EstimatedDeliveryDate' => ['date'],
            'LatestBookingDateTime' => ['date'],
            'Rate' => ['numeric'],
            'ExtraCharges' => ['numeric'],
            'FuelCharge' => ['numeric'],
            'InsuranceCharge' => ['numeric'],
            'Vat' => ['numeric'],
            'TotalCost' => ['numeric'],
            'Currency' => ['string'],
            'VolumetricWeight' => ['int'],
            'WeightUnit' => ['string'],
        ];
    }
}
