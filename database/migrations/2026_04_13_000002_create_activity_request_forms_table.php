<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_request_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('rso_name', 255);
            $table->string('activity_title', 255);
            $table->string('partner_entities', 255)->nullable();
            $table->json('nature_of_activity')->nullable();
            $table->string('nature_other', 255)->nullable();
            $table->json('activity_types')->nullable();
            $table->string('activity_type_other', 255)->nullable();
            $table->string('target_sdg', 64);
            $table->decimal('proposed_budget', 12, 2);
            $table->string('budget_source', 255);
            $table->date('activity_date');
            $table->string('venue', 255);
            $table->boolean('request_letter_has_rationale')->default(false);
            $table->boolean('request_letter_has_objectives')->default(false);
            $table->boolean('request_letter_has_program')->default(false);
            $table->string('request_letter_path', 500);
            $table->string('speaker_resume_path', 500)->nullable();
            $table->string('post_survey_form_path', 500);
            $table->timestamp('used_for_proposal_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_request_forms');
    }
};
