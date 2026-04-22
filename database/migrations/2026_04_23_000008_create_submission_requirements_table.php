<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('organization_submissions')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('requirement_key', 80);
            $table->string('label', 150);
            $table->boolean('is_submitted')->default(false);
            $table->timestamps();

            $table->unique(['submission_id', 'requirement_key'], 'uq_submission_requirements_submission_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_requirements');
    }
};
