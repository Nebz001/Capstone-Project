<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('file_type', 60);
            $table->string('original_name', 255);
            $table->string('stored_path', 500);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('file_size_kb')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
