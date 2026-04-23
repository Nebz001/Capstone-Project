<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DummyUserAccountsSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure redesigned role rows exist before resolving role_id mappings.
        $this->call(RolesTableSeeder::class);

        $rolesByKey = [
            'sdao_approver' => $this->requireRoleId('sdao_staff'),
            'organization_officer' => $this->requireRoleId('rso_president'),
            'adviser' => $this->requireRoleId('adviser'),
            'program_chair' => $this->requireRoleId('program_chair'),
            'dean' => $this->requireRoleId('dean'),
        ];

        $users = [
            [
                'email' => 'carljustin.magpantay@nu-lipa.edu.ph',
                'first_name' => 'Carl Justin',
                'last_name' => 'Magpantay',
                'school_id' => 'EMP-SDAO-0001',
                'role_key' => 'sdao_approver',
                'account_status' => 'ACTIVE',
                'officer_validation_status' => 'PENDING',
            ],
            [
                'email' => 'zairajoy.endayo@nu-lipa.edu.ph',
                'first_name' => 'Zaira Joy',
                'last_name' => 'Endayo',
                'school_id' => 'EMP-SDAO-0002',
                'role_key' => 'sdao_approver',
                'account_status' => 'ACTIVE',
                'officer_validation_status' => 'PENDING',
            ],
            [
                'email' => 'tanbm@students.nu-lipa.edu.ph',
                'first_name' => 'Tan',
                'last_name' => 'Bm',
                'school_id' => 'STU-ORG-0001',
                'role_key' => 'organization_officer',
                'account_status' => 'ACTIVE',
                'officer_validation_status' => 'APPROVED',
            ],
            [
                'email' => 'alcantarakd@students.nu-lipa.edu.ph',
                'first_name' => 'Alcantara',
                'last_name' => 'Kd',
                'school_id' => 'STU-ORG-0002',
                'role_key' => 'organization_officer',
                'account_status' => 'ACTIVE',
                'officer_validation_status' => 'APPROVED',
            ],
            [
                'email' => 'marvin.atanacio@nu-lipa.edu.ph',
                'first_name' => 'Marvin',
                'last_name' => 'Atanacio',
                'school_id' => 'EMP-ADV-0001',
                'role_key' => 'adviser',
                'account_status' => 'ACTIVE',
                'officer_validation_status' => 'PENDING',
            ],
            [
                'email' => 'boybi.aramil@nu-lipa.edu.ph',
                'first_name' => 'Boybi',
                'last_name' => 'Aramil',
                'school_id' => 'EMP-PC-0001',
                'role_key' => 'program_chair',
                'account_status' => 'ACTIVE',
                'officer_validation_status' => 'PENDING',
            ],
            [
                'email' => 'carol.matira@nu-lipa.edu.ph',
                'first_name' => 'Carol',
                'last_name' => 'Matira',
                'school_id' => 'EMP-DEAN-0001',
                'role_key' => 'dean',
                'account_status' => 'ACTIVE',
                'officer_validation_status' => 'PENDING',
            ],
        ];

        $seededUsers = [];

        foreach ($users as $user) {
            $isOrganizationOfficer = $user['role_key'] === 'organization_officer';

            $seededUsers[$user['email']] = User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'school_id' => $user['school_id'],
                    'password' => Hash::make('password1234'),
                    'role_id' => $rolesByKey[$user['role_key']],
                    'account_status' => $user['account_status'],
                    'officer_validation_status' => $user['officer_validation_status'],
                    'officer_validation_notes' => $isOrganizationOfficer
                        ? 'Validated seeded organization officer for testing.'
                        : null,
                    'officer_validated_at' => $isOrganizationOfficer ? now() : null,
                    'officer_validated_by' => null,
                ]
            );
        }

        // Link seeded organization officers to a seeded SDAO approver as validator.
        $officerValidatorId = $seededUsers['carljustin.magpantay@nu-lipa.edu.ph']->id
            ?? User::query()->where('role_id', $rolesByKey['sdao_approver'])->value('id');

        if ($officerValidatorId) {
            User::query()
                ->whereIn('email', [
                    'tanbm@students.nu-lipa.edu.ph',
                    'alcantarakd@students.nu-lipa.edu.ph',
                ])
                ->update([
                    'officer_validated_by' => (int) $officerValidatorId,
                    'officer_validation_notes' => 'Validated by seeded SDAO approver for testing.',
                ]);
        }
    }

    private function requireRoleId(string $roleName): int
    {
        $roleId = Role::query()->where('name', $roleName)->value('id');
        if (! $roleId) {
            throw new \RuntimeException("Role '{$roleName}' was not found. Seed roles first.");
        }

        return (int) $roleId;
    }
}
