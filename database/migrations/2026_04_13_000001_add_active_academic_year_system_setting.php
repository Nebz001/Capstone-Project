<?php

use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('system_settings')
            ->where('key', 'active_academic_year')
            ->exists();

        if (! $exists) {
            SystemSetting::put('active_academic_year', SystemSetting::defaultAcademicYear());
        }
    }

    public function down(): void
    {
        DB::table('system_settings')
            ->where('key', 'active_academic_year')
            ->delete();
    }
};
