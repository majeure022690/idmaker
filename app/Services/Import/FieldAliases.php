<?php

namespace App\Services\Import;

use App\Support\FieldKey;

class FieldAliases
{
    private const ALIASES = [
        'id_number' => ['id_number', 'employee_id', 'id_no', 'employee_no', 'beneficiary_id', 'control_number', 'idno'],
        'full_name' => ['full_name', 'name', 'employee_name', 'complete_name', 'fullname'],
        'first_name' => ['first_name', 'fname', 'given_name', 'firstname'],
        'middle_name' => ['middle_name', 'mname', 'middlename'],
        'last_name' => ['last_name', 'lname', 'surname', 'lastname'],
        'position' => ['position', 'job_title', 'designation', 'title'],
        'office' => ['office', 'branch', 'unit'],
        'department' => ['department', 'dept'],
        'date_of_birth' => ['date_of_birth', 'dob', 'birthdate', 'birth_date'],
        'address' => ['address', 'home_address'],
        'contact_number' => ['contact_number', 'phone', 'mobile', 'contact_no', 'phone_number'],
        'date_issued' => ['date_issued', 'issued_date'],
        'expiration_date' => ['expiration_date', 'expiry_date', 'valid_until'],
    ];

    public static function suggest(string $header): string
    {
        $normalized = FieldKey::normalize($header);

        foreach (self::ALIASES as $canonical => $aliases) {
            if (in_array($normalized, $aliases, true)) {
                return $canonical;
            }
        }

        return $normalized;
    }
}
