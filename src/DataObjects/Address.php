<?php
declare(strict_types=1);
namespace Mcpuishor\ApcCourierDriver\DataObjects;


use Mcpuishor\ApcCourierDriver\DataObjects\Contact;

class Address extends \Mcpuishor\CourierManager\DataObjects\Address {

    public static function fromResponse(\stdClass $address) : self
    {
        return new static([
            'company' => $address->CompanyName,
            'line1' => $address->AddressLine1,
            'line2' => $address->AddressLine2,
            'postcode' => $address->PostalCode,
            'city' => $address->City,
            'county' => $address->County,
            'country' => $address->CountryCode,
            'contact' => Contact::fromResponse($address->Contact),
     ]);
    }
}
