<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_request_forms')) {
            return;
        }

        Schema::table('activity_request_forms', function (Blueprint $table): void {
            if (! Schema::hasColumn('activity_request_forms', 'submitted_by')) {
                $table->foreignId('submitted_by')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('users')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            }

            if (! Schema::hasColumn('activity_request_forms', 'promoted_to_proposal_id')) {
                $table->foreignId('promoted_to_proposal_id')
                    ->nullable()
                    ->after('activity_calendar_entry_id')
                    ->constrained('activity_proposals')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            }

            if (! Schema::hasColumn('activity_request_forms', 'promoted_at')) {
                $table->timestamp('promoted_at')->nullable()->after('promoted_to_proposal_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('activity_request_forms')) {
            return;
        }

        Schema::table('activity_request_forms', function (Blueprint $table): void {
            if (Schema::hasColumn('activity_request_forms', 'promoted_at')) {
                $table->dropColumn('promoted_at');
            }

            if (Schema::hasColumn('activity_request_forms', 'promoted_to_proposal_id')) {
                $table->dropConstrainedForeignId('promoted_to_proposal_id');
            }

            if (Schema::hasColumn('activity_request_forms', 'submitted_by')) {
                $table->dropConstrainedForeignId('submitted_by');
            }
        });
    }
};
