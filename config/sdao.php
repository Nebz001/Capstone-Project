<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SDAO web admin accounts (Super Admins)
    |--------------------------------------------------------------------------
    |
    | Staff accounts that may use the SDAO admin dashboard and signatory flows.
    | Only these records (role ADMIN + email listed here) are treated as SDAO admins.
    |
    | Super Admins have full admin access and may use organization-side workflows
    | (registration, renewal, calendars, etc.) using the admin UI. Optional
    | `school_id` identifies the SDAO staff account in listings and seeders.
    |
    */

    'admin_accounts' => [
        [
            'email' => 'carljustin.magpantay@nu-lipa.edu.ph',
            'first_name' => 'Carl Justin',
            'last_name' => 'Magpantay',
            'school_id' => 'SDAO-ADM-001',
        ],
        [
            'email' => 'zairajoy.enayo@nu-lipa.edu.ph',
            'first_name' => 'Zaira Joy',
            'last_name' => 'Enayo',
            'school_id' => 'SDAO-ADM-002',
        ],
    ],

];
