<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reflects the activity_reports table already present in the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('activity_proposals')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->date('report_submission_date')->nullable();
            $table->string('report_file', 255)->nullable();
            $table->text('accomplishment_summary')->nullable();
            $table->enum('report_status', ['PENDING', 'REVIEWED', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_reports');
    }
};
