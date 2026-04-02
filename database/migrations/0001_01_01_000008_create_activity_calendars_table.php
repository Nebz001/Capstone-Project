<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reflects the activity_calendars table already present in the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('academic_year', 50)->nullable();
            $table->string('semester', 50)->nullable();
            $table->string('calendar_file', 255)->nullable();
            $table->date('submission_date')->nullable();
            $table->enum('calendar_status', ['PENDING', 'APPROVED', 'REJECTED', 'REVISION'])->default('PENDING');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_calendars');
    }
};
