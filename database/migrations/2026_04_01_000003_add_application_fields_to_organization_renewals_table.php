<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds columns to organization_renewals so the table captures every field
 * on the Student Organization Application Form (RENEWAL flow).
 *
 * The renewal checklist has three additional items compared to new registration:
 *   - List of Past Projects
 *   - Financial Statement of the Previous AY
 *   - Summary of Evaluation of Past Projects
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_renewals', function (Blueprint $table) {
            // ── 1. Submission context ────────────────────────────────
            $table->string('academic_year', 20)->nullable()->after('user_id');
            $table->string('contact_person', 255)->nullable()->after('academic_year');
            $table->string('contact_no', 20)->nullable()->after('contact_person');
            $table->string('contact_email', 150)->nullable()->after('contact_no');

            // ── 2. Requirements checklist (renewal) ─────────────────
            $table->boolean('req_letter_of_intent')->default(false)->after('renewal_notes');
            $table->boolean('req_application_form')->default(false)->after('req_letter_of_intent');
            $table->boolean('req_by_laws')->default(false)->after('req_application_form');
            $table->boolean('req_officers_list')->default(false)->after('req_by_laws');
            $table->boolean('req_dean_endorsement')->default(false)->after('req_officers_list');
            $table->boolean('req_proposed_projects')->default(false)->after('req_dean_endorsement');
            $table->boolean('req_past_projects')->default(false)->after('req_proposed_projects');
            $table->boolean('req_financial_statement')->default(false)->after('req_past_projects');
            $table->boolean('req_evaluation_summary')->default(false)->after('req_financial_statement');
            $table->boolean('req_others')->default(false)->after('req_evaluation_summary');
            $table->string('req_others_specify', 255)->nullable()->after('req_others');

            // ── 3. Endorsement / received-by tracking ───────────────
            $table->string('endorsed_by_adviser', 100)->nullable()->after('req_others_specify');
            $table->string('endorsed_by_dean', 100)->nullable()->after('endorsed_by_adviser');
            $table->string('received_by_sdao', 100)->nullable()->after('endorsed_by_dean');
            $table->string('received_by_crso', 100)->nullable()->after('received_by_sdao');
            $table->date('endorsement_date')->nullable()->after('received_by_crso');
            $table->time('endorsement_time')->nullable()->after('endorsement_date');

            // ── 4. Approval decision ────────────────────────────────
            $table->enum('approval_decision', ['APPROVED', 'PROBATION'])->nullable()->after('endorsement_time');
            $table->string('approved_by_sdao', 100)->nullable()->after('approval_decision');
            $table->string('approved_by_crso', 100)->nullable()->after('approved_by_sdao');
            $table->date('approval_date')->nullable()->after('approved_by_crso');

            // ── 5. Additional remarks ───────────────────────────────
            $table->text('additional_remarks')->nullable()->after('approval_date');
        });
    }

    public function down(): void
    {
        Schema::table('organization_renewals', function (Blueprint $table) {
            $table->dropColumn([
                'academic_year',
                'contact_person',
                'contact_no',
                'contact_email',
                'req_letter_of_intent',
                'req_application_form',
                'req_by_laws',
                'req_officers_list',
                'req_dean_endorsement',
                'req_proposed_projects',
                'req_past_projects',
                'req_financial_statement',
                'req_evaluation_summary',
                'req_others',
                'req_others_specify',
                'endorsed_by_adviser',
                'endorsed_by_dean',
                'received_by_sdao',
                'received_by_crso',
                'endorsement_date',
                'endorsement_time',
                'approval_decision',
                'approved_by_sdao',
                'approved_by_crso',
                'approval_date',
                'additional_remarks',
            ]);
        });
    }
};
