<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_reports')) {
            return;
        }

        Schema::table('activity_reports', function (Blueprint $table): void {
            if (! Schema::hasColumn('activity_reports', 'status')) {
                $table->enum('status', ['draft', 'pending', 'under_review', 'approved', 'rejected', 'revision'])->default('draft')->after('report_status');
            }
        });

        if (Schema::hasColumn('activity_reports', 'report_status')) {
            DB::table('activity_reports')->update([
                'status' => DB::raw(
                    "CASE report_status
                        WHEN 'APPROVED' THEN 'approved'
                        WHEN 'REJECTED' THEN 'rejected'
                        WHEN 'REVIEWED' THEN 'under_review'
                        ELSE 'pending'
                    END"
                ),
            ]);
        }

        if (Schema::hasColumn('activity_reports', 'proposal_id') && Schema::hasColumn('activity_reports', 'activity_proposal_id')) {
            DB::table('activity_reports')->whereNull('activity_proposal_id')->update(['activity_proposal_id' => DB::raw('proposal_id')]);
        }
        if (Schema::hasColumn('activity_reports', 'user_id') && Schema::hasColumn('activity_reports', 'submitted_by')) {
            DB::table('activity_reports')->whereNull('submitted_by')->update(['submitted_by' => DB::raw('user_id')]);
        }
        if (Schema::hasColumn('activity_reports', 'activity_event_title') && Schema::hasColumn('activity_reports', 'event_title')) {
            DB::table('activity_reports')->whereNull('event_title')->update(['event_title' => DB::raw('activity_event_title')]);
        }
        if (Schema::hasColumn('activity_reports', 'event_name') && Schema::hasColumn('activity_reports', 'event_title')) {
            DB::table('activity_reports')->whereNull('event_title')->update(['event_title' => DB::raw('event_name')]);
        }

        Schema::table('activity_reports', function (Blueprint $table): void {
            if (Schema::hasColumn('activity_reports', 'proposal_id')) {
                $table->dropConstrainedForeignId('proposal_id');
            }
            if (Schema::hasColumn('activity_reports', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }

            foreach ([
                'report_file',
                'report_status',
                'activity_event_title',
                'event_name',
                'poster_image_path',
                'supporting_photo_paths',
                'certificate_sample_path',
                'evaluation_form_sample_path',
                'attendance_sheet_path',
            ] as $column) {
                if (Schema::hasColumn('activity_reports', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('activity_reports')) {
            return;
        }

        Schema::table('activity_reports', function (Blueprint $table): void {
            if (! Schema::hasColumn('activity_reports', 'proposal_id')) {
                $table->foreignId('proposal_id')->nullable()->after('id')->constrained('activity_proposals')->nullOnDelete()->cascadeOnUpdate();
            }
            if (! Schema::hasColumn('activity_reports', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('organization_id')->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            }
            if (! Schema::hasColumn('activity_reports', 'report_file')) {
                $table->string('report_file', 255)->nullable();
            }
            if (! Schema::hasColumn('activity_reports', 'report_status')) {
                $table->enum('report_status', ['PENDING', 'REVIEWED', 'APPROVED', 'REJECTED'])->default('PENDING');
            }
            if (! Schema::hasColumn('activity_reports', 'activity_event_title')) {
                $table->string('activity_event_title', 255)->nullable();
            }
            if (! Schema::hasColumn('activity_reports', 'event_name')) {
                $table->string('event_name', 255)->nullable();
            }
            if (! Schema::hasColumn('activity_reports', 'poster_image_path')) {
                $table->string('poster_image_path', 255)->nullable();
            }
            if (! Schema::hasColumn('activity_reports', 'supporting_photo_paths')) {
                $table->json('supporting_photo_paths')->nullable();
            }
            if (! Schema::hasColumn('activity_reports', 'certificate_sample_path')) {
                $table->string('certificate_sample_path', 255)->nullable();
            }
            if (! Schema::hasColumn('activity_reports', 'evaluation_form_sample_path')) {
                $table->string('evaluation_form_sample_path', 255)->nullable();
            }
            if (! Schema::hasColumn('activity_reports', 'attendance_sheet_path')) {
                $table->string('attendance_sheet_path', 255)->nullable();
            }
        });
    }
};

