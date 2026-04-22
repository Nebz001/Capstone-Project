<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('approvable');
            $table->foreignId('workflow_step_id')->nullable()->constrained('approval_workflow_steps')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->enum('action', ['submitted', 'approved', 'rejected', 'revision_requested', 'resubmitted', 'recalled', 'cancelled']);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->text('comments')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_logs');
    }
};
