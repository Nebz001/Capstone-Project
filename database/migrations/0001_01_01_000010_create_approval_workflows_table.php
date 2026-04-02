<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reflects the approval_workflows table already present in the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('activity_proposals')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('office_id')->constrained('offices')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->integer('approval_level');
            $table->boolean('current_step')->default(false);
            $table->date('review_date')->nullable();
            $table->dateTime('acted_at')->nullable();
            $table->enum('decision_status', ['PENDING', 'APPROVED', 'REJECTED', 'REVISION_REQUIRED'])->default('PENDING');
            $table->text('review_comments')->nullable();
            $table->timestamps();

            $table->unique(['proposal_id', 'approval_level'], 'uq_approval_workflows_proposal_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_workflows');
    }
};
