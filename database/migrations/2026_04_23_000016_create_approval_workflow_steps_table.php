<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->morphs('approvable');
            $table->unsignedTinyInteger('step_order');
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->enum('status', ['pending', 'approved', 'rejected', 'revision_required', 'skipped'])->default('pending');
            $table->boolean('is_current_step')->default(false);
            $table->text('review_comments')->nullable();
            $table->dateTime('acted_at')->nullable();
            $table->timestamps();

            $table->unique(['approvable_type', 'approvable_id', 'step_order'], 'uq_workflow_steps_approvable_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_workflow_steps');
    }
};
