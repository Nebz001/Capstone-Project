<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesTableSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'rso_president', 'display_name' => 'RSO President', 'approval_level' => null],
            ['name' => 'adviser', 'display_name' => 'Adviser', 'approval_level' => 1],
            ['name' => 'program_chair', 'display_name' => 'Program Chair', 'approval_level' => 2],
            ['name' => 'dean', 'display_name' => 'Dean', 'approval_level' => 3],
            ['name' => 'academic_director', 'display_name' => 'Academic Director', 'approval_level' => 4],
            ['name' => 'executive_director', 'display_name' => 'Executive Director', 'approval_level' => 5],
            ['name' => 'sdao_staff', 'display_name' => 'SDAO Staff', 'approval_level' => 6],
            ['name' => 'admin', 'display_name' => 'Admin', 'approval_level' => 7],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['name' => $role['name']],
                [
                    'display_name' => $role['display_name'],
                    'approval_level' => $role['approval_level'],
                ]
            );
        }
    }
}
