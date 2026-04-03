<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try {
            Schema::table('activity_reports', function (Blueprint $table) {
                $table->dropForeign(['proposal_id']);
            });
        } catch (Throwable) {
            // Foreign key may already have been dropped (e.g. partial migration run).
        }

        Schema::table('activity_reports', function (Blueprint $table) {
            $table->unsignedBigInteger('proposal_id')->nullable()->change();

            $table->string('activity_event_title', 255)->nullable();
            $table->string('school_code', 32)->nullable();
            $table->string('department', 150)->nullable();
            $table->string('poster_image_path', 255)->nullable();
            $table->string('event_name', 255)->nullable();
            $table->dateTime('event_starts_at')->nullable();
            $table->string('activity_chairs', 500)->nullable();
            $table->string('prepared_by', 255)->nullable();
            $table->text('program_content')->nullable();
            $table->json('supporting_photo_paths')->nullable();
            $table->string('certificate_sample_path', 255)->nullable();
            $table->text('evaluation_report')->nullable();
            $table->decimal('participants_reached_percent', 5, 2)->nullable();
            $table->string('evaluation_form_sample_path', 255)->nullable();
            $table->string('attendance_sheet_path', 255)->nullable();
        });

        try {
            Schema::table('activity_reports', function (Blueprint $table) {
                $table->foreign('proposal_id')
                    ->references('id')
                    ->on('activity_proposals')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            });
        } catch (Throwable) {
            // Constraint may already exist.
        }
    }

    public function down(): void
    {
        Schema::table('activity_reports', function (Blueprint $table) {
            $table->dropForeign(['proposal_id']);
        });

        Schema::table('activity_reports', function (Blueprint $table) {
            $table->dropColumn([
                'activity_event_title',
                'school_code',
                'department',
                'poster_image_path',
                'event_name',
                'event_starts_at',
                'activity_chairs',
                'prepared_by',
                'program_content',
                'supporting_photo_paths',
                'certificate_sample_path',
                'evaluation_report',
                'participants_reached_percent',
                'evaluation_form_sample_path',
                'attendance_sheet_path',
            ]);
        });

        Schema::table('activity_reports', function (Blueprint $table) {
            $table->unsignedBigInteger('proposal_id')->nullable(false)->change();
        });

        Schema::table('activity_reports', function (Blueprint $table) {
            $table->foreign('proposal_id')
                ->references('id')
                ->on('activity_proposals')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }
};
