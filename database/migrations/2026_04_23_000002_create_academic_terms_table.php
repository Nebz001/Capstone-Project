<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_terms', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year', 20);
            $table->enum('semester', ['first', 'second', 'midyear']);
            $table->date('starts_at');
            $table->date('ends_at');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_terms');
    }
};
