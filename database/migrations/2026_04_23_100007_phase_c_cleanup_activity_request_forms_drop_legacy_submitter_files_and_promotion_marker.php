<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_request_forms')) {
            return;
        }

        if (Schema::hasColumn('activity_request_forms', 'user_id') && Schema::hasColumn('activity_request_forms', 'submitted_by')) {
            DB::table('activity_request_forms')->whereNull('submitted_by')->update(['submitted_by' => DB::raw('user_id')]);
        }

        Schema::table('activity_request_forms', function (Blueprint $table): void {
            if (Schema::hasColumn('activity_request_forms', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }

            foreach ([
                'rso_name',
                'request_letter_has_rationale',
                'request_letter_has_objectives',
                'request_letter_has_program',
                'request_letter_path',
                'speaker_resume_path',
                'post_survey_form_path',
                'used_for_proposal_at',
            ] as $column) {
                if (Schema::hasColumn('activity_request_forms', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('activity_request_forms')) {
            return;
        }

        Schema::table('activity_request_forms', function (Blueprint $table): void {
            if (! Schema::hasColumn('activity_request_forms', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('organization_id')->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            }
            if (! Schema::hasColumn('activity_request_forms', 'rso_name')) {
                $table->string('rso_name', 255)->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('activity_request_forms', 'request_letter_has_rationale')) {
                $table->boolean('request_letter_has_rationale')->default(false);
            }
            if (! Schema::hasColumn('activity_request_forms', 'request_letter_has_objectives')) {
                $table->boolean('request_letter_has_objectives')->default(false);
            }
            if (! Schema::hasColumn('activity_request_forms', 'request_letter_has_program')) {
                $table->boolean('request_letter_has_program')->default(false);
            }
            if (! Schema::hasColumn('activity_request_forms', 'request_letter_path')) {
                $table->string('request_letter_path', 500)->nullable();
            }
            if (! Schema::hasColumn('activity_request_forms', 'speaker_resume_path')) {
                $table->string('speaker_resume_path', 500)->nullable();
            }
            if (! Schema::hasColumn('activity_request_forms', 'post_survey_form_path')) {
                $table->string('post_survey_form_path', 500)->nullable();
            }
            if (! Schema::hasColumn('activity_request_forms', 'used_for_proposal_at')) {
                $table->timestamp('used_for_proposal_at')->nullable();
            }
        });
    }
};

