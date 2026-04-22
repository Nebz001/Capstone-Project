<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_calendar_entries')) {
            return;
        }

        Schema::table('activity_calendar_entries', function (Blueprint $table): void {
            if (! Schema::hasColumn('activity_calendar_entries', 'target_sdg')) {
                $table->string('target_sdg', 64)->nullable()->after('sdg');
            }

            if (! Schema::hasColumn('activity_calendar_entries', 'target_participants')) {
                $table->string('target_participants', 255)->nullable()->after('participant_program');
            }

            if (! Schema::hasColumn('activity_calendar_entries', 'target_program')) {
                $table->string('target_program', 255)->nullable()->after('target_participants');
            }

            if (! Schema::hasColumn('activity_calendar_entries', 'estimated_budget')) {
                $table->decimal('estimated_budget', 12, 2)->nullable()->after('budget');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('activity_calendar_entries')) {
            return;
        }

        Schema::table('activity_calendar_entries', function (Blueprint $table): void {
            if (Schema::hasColumn('activity_calendar_entries', 'estimated_budget')) {
                $table->dropColumn('estimated_budget');
            }

            if (Schema::hasColumn('activity_calendar_entries', 'target_program')) {
                $table->dropColumn('target_program');
            }

            if (Schema::hasColumn('activity_calendar_entries', 'target_participants')) {
                $table->dropColumn('target_participants');
            }

            if (Schema::hasColumn('activity_calendar_entries', 'target_sdg')) {
                $table->dropColumn('target_sdg');
            }
        });
    }
};
