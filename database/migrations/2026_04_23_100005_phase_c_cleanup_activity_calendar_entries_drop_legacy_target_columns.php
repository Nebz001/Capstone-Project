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
            if (Schema::hasColumn('activity_calendar_entries', 'sdg')) {
                $table->dropColumn('sdg');
            }
            if (Schema::hasColumn('activity_calendar_entries', 'participant_program')) {
                $table->dropColumn('participant_program');
            }
            if (Schema::hasColumn('activity_calendar_entries', 'budget')) {
                $table->dropColumn('budget');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('activity_calendar_entries')) {
            return;
        }

        Schema::table('activity_calendar_entries', function (Blueprint $table): void {
            if (! Schema::hasColumn('activity_calendar_entries', 'sdg')) {
                $table->string('sdg', 64)->nullable()->after('activity_name');
            }
            if (! Schema::hasColumn('activity_calendar_entries', 'participant_program')) {
                $table->text('participant_program')->nullable()->after('venue');
            }
            if (! Schema::hasColumn('activity_calendar_entries', 'budget')) {
                $table->string('budget', 255)->nullable()->after('participant_program');
            }
        });
    }
};

