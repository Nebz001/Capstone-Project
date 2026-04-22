<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_calendars')) {
            return;
        }

        Schema::table('activity_calendars', function (Blueprint $table): void {
            if (! Schema::hasColumn('activity_calendars', 'academic_term_id')) {
                $table->foreignId('academic_term_id')
                    ->nullable()
                    ->after('semester')
                    ->constrained('academic_terms')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            }

            if (! Schema::hasColumn('activity_calendars', 'submitted_by')) {
                $table->foreignId('submitted_by')
                    ->nullable()
                    ->after('organization_id')
                    ->constrained('users')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            }

            if (! Schema::hasColumn('activity_calendars', 'current_approval_step')) {
                $table->unsignedTinyInteger('current_approval_step')
                    ->default(0)
                    ->after('calendar_status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('activity_calendars')) {
            return;
        }

        Schema::table('activity_calendars', function (Blueprint $table): void {
            if (Schema::hasColumn('activity_calendars', 'academic_term_id')) {
                $table->dropConstrainedForeignId('academic_term_id');
            }

            if (Schema::hasColumn('activity_calendars', 'submitted_by')) {
                $table->dropConstrainedForeignId('submitted_by');
            }

            if (Schema::hasColumn('activity_calendars', 'current_approval_step')) {
                $table->dropColumn('current_approval_step');
            }
        });
    }
};
