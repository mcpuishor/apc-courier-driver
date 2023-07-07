<?php

return [
    'username' => env('APC_COURIER_DRIVER_USERNAME', ''),
    'password' => env('APC_COURIER_DRIVER_PASSWORD', ''),

    'content' => 'application/json',

    'mode' => 'training',

    'endpoints' => [
        'training' => 'https://apc-training.hypaship.com/api/3.0/',
        'live' => 'https://apc.hypaship.com/api/3.0/',

        'services' => 'ServiceAvailability',
        'book' => 'Orders',
        'label' => 'Orders/{waybill}.json?searchtype=CarrierWaybill',
        'tracks' => 'Tracks/{waybill}.json?searchtype=CarrierWaybill&history=Yes',
    ],

    'businessday' => [
        'ready' => env('APC_COURIER_DRIVER_READY', '09:00'),
        'close' => env('APC_COURIER_DRIVER_CLOSE', '17:00'),
    ],

    'labels' => [
        'format' => 'PDF'
    ]
];
