<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reflects the communication_threads table already present in the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('proposal_id')->nullable()->constrained('activity_proposals')->nullOnDelete()->cascadeOnUpdate();
            $table->string('thread_subject', 200);
            $table->enum('thread_type', ['PROPOSAL', 'REGISTRATION', 'RENEWAL', 'REPORT']);
            $table->enum('thread_status', ['OPEN', 'CLOSED'])->default('OPEN');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_threads');
    }
};
