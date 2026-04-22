<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('academic_term_id')->constrained('academic_terms')->cascadeOnDelete()->cascadeOnUpdate();
            $table->enum('type', ['registration', 'renewal']);
            $table->string('contact_person', 255)->nullable();
            $table->string('contact_no', 20)->nullable();
            $table->string('contact_email', 150)->nullable();
            $table->date('submission_date');
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'pending', 'under_review', 'approved', 'rejected', 'revision'])->default('draft');
            $table->unsignedTinyInteger('current_approval_step')->default(0);
            $table->text('additional_remarks')->nullable();
            $table->enum('approval_decision', ['approved', 'probation'])->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_submissions');
    }
};
