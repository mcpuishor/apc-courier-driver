<?php
namespace Mcpuishor\ApcCourierDriver\Facades;

use Illuminate\Support\Facades\Facade;

class ApcCourier extends Facade
{
    public static function getFacadeAccessor() : string
    {
        return 'apc-courier-driver';
    }
}
