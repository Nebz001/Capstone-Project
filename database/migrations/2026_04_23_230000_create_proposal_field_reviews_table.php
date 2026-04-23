<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_field_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_proposal_id')->constrained('activity_proposals')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('workflow_step_id')->constrained('approval_workflow_steps')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('field_key', 120);
            $table->string('field_label', 255);
            $table->enum('status', ['approved', 'revision', 'rejected']);
            $table->text('comment')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['activity_proposal_id', 'workflow_step_id', 'field_key'], 'uq_proposal_field_review_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_field_reviews');
    }
};
