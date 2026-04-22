<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('organization_registrations');
    }

    public function down(): void
    {
        if (Schema::hasTable('organization_registrations')) {
            return;
        }

        Schema::create('organization_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('contact_person', 255)->nullable();
            $table->string('contact_no', 30)->nullable();
            $table->string('contact_email', 150)->nullable();
            $table->date('submission_date')->nullable();
            $table->enum('registration_status', ['PENDING', 'UNDER_REVIEW', 'APPROVED', 'REJECTED', 'REVISION'])->default('PENDING');
            $table->text('registration_notes')->nullable();
            $table->timestamps();
        });
    }
};

