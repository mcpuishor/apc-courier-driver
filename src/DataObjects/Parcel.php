<?php
declare(strict_types=1);
namespace Mcpuishor\ApcCourierDriver\DataObjects;

class Parcel extends \Mcpuishor\CourierManager\DataObjects\Parcel
{
    public function map(): array
    {
        return [
            'width' => 'Width',
            'length' => 'Length',
            'height' => 'Height',
            'weight' => 'Weight',
        ];
    }

    public function inputMap(): array
    {
        return [
            'Width' => 'width',
            'Length' => 'length',
            'Height' => 'height',
            'Weight' => 'weight',
        ];
    }
}
