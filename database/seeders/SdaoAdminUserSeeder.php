<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds SDAO staff accounts from config/sdao.php (admin_accounts).
 * role_type ADMIN is the system's SDAO admin role (see users.role_type enum).
 *
 * Login password (testing): SdaoAdmin123!
 */
class SdaoAdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $plainPassword = 'SdaoAdmin123!';

        foreach (config('sdao.admin_accounts') as $account) {
            User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'first_name' => $account['first_name'],
                    'last_name' => $account['last_name'],
                    'school_id' => $account['school_id'],
                    'password' => $plainPassword,
                    'role_type' => 'ADMIN',
                    'account_status' => 'ACTIVE',
                ]
            );
        }
    }
}
