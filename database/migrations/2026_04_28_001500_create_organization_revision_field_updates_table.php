<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_revision_field_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_submission_id')->constrained('organization_submissions')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('section_key', 80);
            $table->string('field_key', 120);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->json('old_file_meta')->nullable();
            $table->json('new_file_meta')->nullable();
            $table->dateTime('resubmitted_at');
            $table->foreignId('resubmitted_by')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->dateTime('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_submission_id', 'section_key', 'field_key'], 'idx_org_rev_updates_lookup');
            $table->index(['organization_submission_id', 'acknowledged_at'], 'idx_org_rev_updates_ack');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_revision_field_updates');
    }
};

