<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * This migration reflects the organizations table that already exists in the database.
 * It was reconstructed from the live SQL schema so the Laravel codebase stays in sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('organization_name', 150);
            $table->string('organization_type', 50);
            $table->string('college_department', 100);
            $table->string('adviser_name', 100)->nullable();
            $table->date('founded_date')->nullable();
            $table->enum('organization_status', ['ACTIVE', 'INACTIVE', 'PENDING', 'SUSPENDED'])->default('PENDING');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
