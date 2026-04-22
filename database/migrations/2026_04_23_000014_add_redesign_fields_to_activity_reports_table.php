<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_reports')) {
            return;
        }

        Schema::table('activity_reports', function (Blueprint $table): void {
            if (! Schema::hasColumn('activity_reports', 'activity_proposal_id')) {
                $table->foreignId('activity_proposal_id')
                    ->nullable()
                    ->after('proposal_id')
                    ->constrained('activity_proposals')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            }

            if (! Schema::hasColumn('activity_reports', 'submitted_by')) {
                $table->foreignId('submitted_by')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('users')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            }

            if (! Schema::hasColumn('activity_reports', 'event_title')) {
                $table->string('event_title', 255)->nullable()->after('event_name');
            }

            if (! Schema::hasColumn('activity_reports', 'event_starts_at')) {
                $table->dateTime('event_starts_at')->nullable()->after('event_title');
            }

            if (! Schema::hasColumn('activity_reports', 'event_ends_at')) {
                $table->dateTime('event_ends_at')->nullable()->after('event_starts_at');
            }

            if (! Schema::hasColumn('activity_reports', 'current_approval_step')) {
                $table->unsignedTinyInteger('current_approval_step')
                    ->default(0)
                    ->after('report_status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('activity_reports')) {
            return;
        }

        Schema::table('activity_reports', function (Blueprint $table): void {
            if (Schema::hasColumn('activity_reports', 'current_approval_step')) {
                $table->dropColumn('current_approval_step');
            }

            if (Schema::hasColumn('activity_reports', 'event_ends_at')) {
                $table->dropColumn('event_ends_at');
            }

            if (Schema::hasColumn('activity_reports', 'event_starts_at')) {
                $table->dropColumn('event_starts_at');
            }

            if (Schema::hasColumn('activity_reports', 'event_title')) {
                $table->dropColumn('event_title');
            }

            if (Schema::hasColumn('activity_reports', 'submitted_by')) {
                $table->dropConstrainedForeignId('submitted_by');
            }

            if (Schema::hasColumn('activity_reports', 'activity_proposal_id')) {
                $table->dropConstrainedForeignId('activity_proposal_id');
            }
        });
    }
};
