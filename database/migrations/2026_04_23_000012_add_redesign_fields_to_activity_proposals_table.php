<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_proposals')) {
            return;
        }

        Schema::table('activity_proposals', function (Blueprint $table): void {
            if (! Schema::hasColumn('activity_proposals', 'academic_term_id')) {
                $table->foreignId('academic_term_id')
                    ->nullable()
                    ->after('academic_year')
                    ->constrained('academic_terms')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            }

            if (! Schema::hasColumn('activity_proposals', 'activity_calendar_id')) {
                $table->foreignId('activity_calendar_id')
                    ->nullable()
                    ->after('calendar_id')
                    ->constrained('activity_calendars')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            }

            if (! Schema::hasColumn('activity_proposals', 'activity_calendar_entry_id')) {
                $table->foreignId('activity_calendar_entry_id')
                    ->nullable()
                    ->after('activity_calendar_id')
                    ->constrained('activity_calendar_entries')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            }

            if (! Schema::hasColumn('activity_proposals', 'submitted_by')) {
                $table->foreignId('submitted_by')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('users')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            }

            if (! Schema::hasColumn('activity_proposals', 'proposed_start_time')) {
                $table->time('proposed_start_time')->nullable()->after('proposed_time');
            }

            if (! Schema::hasColumn('activity_proposals', 'proposed_end_time')) {
                $table->time('proposed_end_time')->nullable()->after('proposed_start_time');
            }

            if (! Schema::hasColumn('activity_proposals', 'target_sdg')) {
                $table->string('target_sdg', 64)->nullable()->after('program_flow');
            }

            if (! Schema::hasColumn('activity_proposals', 'current_approval_step')) {
                $table->unsignedTinyInteger('current_approval_step')
                    ->default(0)
                    ->after('proposal_status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('activity_proposals')) {
            return;
        }

        Schema::table('activity_proposals', function (Blueprint $table): void {
            if (Schema::hasColumn('activity_proposals', 'current_approval_step')) {
                $table->dropColumn('current_approval_step');
            }

            if (Schema::hasColumn('activity_proposals', 'proposed_end_time')) {
                $table->dropColumn('proposed_end_time');
            }

            if (Schema::hasColumn('activity_proposals', 'proposed_start_time')) {
                $table->dropColumn('proposed_start_time');
            }

            if (Schema::hasColumn('activity_proposals', 'target_sdg')) {
                $table->dropColumn('target_sdg');
            }

            if (Schema::hasColumn('activity_proposals', 'submitted_by')) {
                $table->dropConstrainedForeignId('submitted_by');
            }

            if (Schema::hasColumn('activity_proposals', 'activity_calendar_id')) {
                $table->dropConstrainedForeignId('activity_calendar_id');
            }

            if (Schema::hasColumn('activity_proposals', 'activity_calendar_entry_id')) {
                $table->dropConstrainedForeignId('activity_calendar_entry_id');
            }

            if (Schema::hasColumn('activity_proposals', 'academic_term_id')) {
                $table->dropConstrainedForeignId('academic_term_id');
            }
        });
    }
};
