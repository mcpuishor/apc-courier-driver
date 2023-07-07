<?php
declare(strict_types=1);
namespace Mcpuishor\ApcCourierDriver\DataObjects;

class Contact extends \Mcpuishor\CourierManager\DataObjects\Contact
{
    public static function fromResponse(\stdClass $contact) : self
    {
        return new static([
            'name' => $contact->PersonName,
            'phone' => $contact->PhoneNumber,
            'email' => $contact->Email,
        ]);
    }
}
