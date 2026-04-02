<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reflects the activity_proposals table already present in the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('calendar_id')->nullable()->constrained('activity_calendars')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('activity_title', 200);
            $table->text('activity_description')->nullable();
            $table->date('proposed_start_date')->nullable();
            $table->date('proposed_end_date')->nullable();
            $table->string('venue', 255)->nullable();
            $table->decimal('estimated_budget', 12, 2)->default(0.00);
            $table->date('submission_date')->nullable();
            $table->enum('proposal_status', ['PENDING', 'UNDER_REVIEW', 'APPROVED', 'REJECTED', 'REVISION'])->default('PENDING');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_proposals');
    }
};
