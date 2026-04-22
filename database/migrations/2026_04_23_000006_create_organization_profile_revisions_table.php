<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_profile_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->text('revision_notes');
            $table->enum('status', ['open', 'addressed'])->default('open');
            $table->timestamp('addressed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_profile_revisions');
    }
};
