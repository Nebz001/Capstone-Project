<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_calendars')) {
            return;
        }

        Schema::table('activity_calendars', function (Blueprint $table): void {
            if (! Schema::hasColumn('activity_calendars', 'status')) {
                $table->enum('status', ['draft', 'pending', 'under_review', 'approved', 'rejected', 'revision'])->default('draft')->after('calendar_status');
            }
        });

        if (Schema::hasColumn('activity_calendars', 'calendar_status')) {
            DB::table('activity_calendars')->update([
                'status' => DB::raw(
                    "CASE calendar_status
                        WHEN 'APPROVED' THEN 'approved'
                        WHEN 'REJECTED' THEN 'rejected'
                        WHEN 'REVISION' THEN 'revision'
                        ELSE 'pending'
                    END"
                ),
            ]);
        }

        Schema::table('activity_calendars', function (Blueprint $table): void {
            foreach (['academic_year', 'semester', 'calendar_file', 'calendar_status', 'submitted_organization_name'] as $column) {
                if (Schema::hasColumn('activity_calendars', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('activity_calendars')) {
            return;
        }

        Schema::table('activity_calendars', function (Blueprint $table): void {
            if (! Schema::hasColumn('activity_calendars', 'academic_year')) {
                $table->string('academic_year', 50)->nullable()->after('organization_id');
            }
            if (! Schema::hasColumn('activity_calendars', 'semester')) {
                $table->string('semester', 50)->nullable()->after('academic_year');
            }
            if (! Schema::hasColumn('activity_calendars', 'calendar_file')) {
                $table->string('calendar_file', 255)->nullable()->after('semester');
            }
            if (! Schema::hasColumn('activity_calendars', 'calendar_status')) {
                $table->enum('calendar_status', ['PENDING', 'APPROVED', 'REJECTED', 'REVISION'])->default('PENDING')->after('submission_date');
            }
            if (! Schema::hasColumn('activity_calendars', 'submitted_organization_name')) {
                $table->string('submitted_organization_name', 255)->nullable()->after('organization_id');
            }
        });
    }
};

