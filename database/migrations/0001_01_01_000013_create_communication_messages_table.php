<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reflects the communication_messages table already present in the database.
 * Note: This table uses sent_at + is_read instead of standard created_at/updated_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('communication_threads')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->text('message_content');
            $table->timestamp('sent_at')->useCurrent();
            $table->boolean('is_read')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_messages');
    }
};
