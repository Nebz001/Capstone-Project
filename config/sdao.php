<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SDAO web admin accounts
    |--------------------------------------------------------------------------
    |
    | Staff accounts that may use the SDAO admin dashboard and signatory flows.
    | Only these records (role ADMIN + email listed here) are treated as SDAO admins.
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
