<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Polymorphic counterpart to `organization_revision_field_updates`.
 *
 * Why a separate table?
 *   - `organization_revision_field_updates` is FK-bound to
 *     `organization_submissions.id`, which is correct for Registration and
 *     Renewal (both are OrganizationSubmissions). Reusing it for
 *     ActivityCalendar / ActivityProposal / ActivityReport would require
 *     dropping that FK and going polymorphic, which would risk regressions in
 *     the *finished* Registration workflow.
 *   - Instead, we keep the existing table untouched and add this polymorphic
 *     companion that ActivityCalendar / ActivityProposal / ActivityReport
 *     officers can write to when they resubmit a flagged field/file.
 *
 * Mirrored shape: section_key + field_key + old/new value or file meta +
 * resubmission audit + acknowledgement audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('module_revision_field_updates')) {
            return;
        }

        Schema::create('module_revision_field_updates', function (Blueprint $table): void {
            $table->id();
            $table->morphs('reviewable');
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

            $table->index(
                ['reviewable_type', 'reviewable_id', 'section_key', 'field_key'],
                'idx_module_rev_updates_lookup'
            );
            $table->index(
                ['reviewable_type', 'reviewable_id', 'acknowledged_at'],
                'idx_module_rev_updates_ack'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_revision_field_updates');
    }
};
