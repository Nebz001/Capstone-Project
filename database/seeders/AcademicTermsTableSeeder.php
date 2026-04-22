<?php

namespace Database\Seeders;

use App\Models\AcademicTerm;
use Illuminate\Database\Seeder;

class AcademicTermsTableSeeder extends Seeder
{
    public function run(): void
    {
        AcademicTerm::query()->firstOrCreate(
            [
                'academic_year' => now()->format('Y').'-'.(now()->year + 1),
                'semester' => 'first',
            ],
            [
                'starts_at' => now()->startOfYear()->toDateString(),
                'ends_at' => now()->startOfYear()->addMonths(5)->endOfMonth()->toDateString(),
                'is_active' => false,
            ]
        );
    }
}
