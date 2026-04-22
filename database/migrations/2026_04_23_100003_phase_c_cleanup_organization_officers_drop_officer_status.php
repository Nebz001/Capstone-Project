<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organization_officers')) {
            return;
        }

        Schema::table('organization_officers', function (Blueprint $table): void {
            if (! Schema::hasColumn('organization_officers', 'status')) {
                $table->enum('status', ['active', 'inactive'])->default('active')->after('officer_status');
            }
        });

        if (Schema::hasColumn('organization_officers', 'officer_status')) {
            DB::table('organization_officers')->update([
                'status' => DB::raw("CASE officer_status WHEN 'INACTIVE' THEN 'inactive' ELSE 'active' END"),
            ]);
        }

        Schema::table('organization_officers', function (Blueprint $table): void {
            if (Schema::hasColumn('organization_officers', 'officer_status')) {
                $table->dropColumn('officer_status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('organization_officers')) {
            return;
        }

        Schema::table('organization_officers', function (Blueprint $table): void {
            if (! Schema::hasColumn('organization_officers', 'officer_status')) {
                $table->enum('officer_status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE')->after('position_title');
            }
        });
    }
};

