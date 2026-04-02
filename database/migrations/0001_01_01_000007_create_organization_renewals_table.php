<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reflects the organization_renewals table already present in the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->date('submission_date');
            $table->string('renewal_document', 255)->nullable();
            $table->text('renewal_notes')->nullable();
            $table->enum('renewal_status', ['PENDING', 'UNDER_REVIEW', 'APPROVED', 'REJECTED', 'REVISION'])->default('PENDING');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_renewals');
    }
};
