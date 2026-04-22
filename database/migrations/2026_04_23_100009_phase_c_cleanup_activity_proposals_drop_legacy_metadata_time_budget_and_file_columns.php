<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_proposals')) {
            return;
        }

        Schema::table('activity_proposals', function (Blueprint $table): void {
            if (! Schema::hasColumn('activity_proposals', 'status')) {
                $table->enum('status', ['draft', 'pending', 'under_review', 'approved', 'rejected', 'revision'])->default('draft')->after('proposal_status');
            }
        });

        if (Schema::hasColumn('activity_proposals', 'proposal_status')) {
            DB::table('activity_proposals')->update([
                'status' => DB::raw(
                    "CASE proposal_status
                        WHEN 'APPROVED' THEN 'approved'
                        WHEN 'REJECTED' THEN 'rejected'
                        WHEN 'REVISION' THEN 'revision'
                        WHEN 'UNDER_REVIEW' THEN 'under_review'
                        ELSE 'pending'
                    END"
                ),
            ]);
        }

        if (Schema::hasColumn('activity_proposals', 'calendar_id') && Schema::hasColumn('activity_proposals', 'activity_calendar_id')) {
            DB::table('activity_proposals')->whereNull('activity_calendar_id')->update(['activity_calendar_id' => DB::raw('calendar_id')]);
        }
        if (Schema::hasColumn('activity_proposals', 'user_id') && Schema::hasColumn('activity_proposals', 'submitted_by')) {
            DB::table('activity_proposals')->whereNull('submitted_by')->update(['submitted_by' => DB::raw('user_id')]);
        }

        Schema::table('activity_proposals', function (Blueprint $table): void {
            if (Schema::hasColumn('activity_proposals', 'calendar_id')) {
                $table->dropConstrainedForeignId('calendar_id');
            }
            if (Schema::hasColumn('activity_proposals', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
            foreach ([
                'proposal_status',
                'form_organization_name',
                'organization_logo_path',
                'school_code',
                'department_program',
                'academic_year',
                'proposed_time',
                'budget_materials_supplies',
                'budget_food_beverage',
                'budget_other_expenses',
                'budget_breakdown_items',
                'resume_resource_persons_path',
                'external_funding_support_path',
            ] as $column) {
                if (Schema::hasColumn('activity_proposals', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('activity_proposals')) {
            return;
        }

        Schema::table('activity_proposals', function (Blueprint $table): void {
            if (! Schema::hasColumn('activity_proposals', 'calendar_id')) {
                $table->foreignId('calendar_id')->nullable()->after('organization_id')->constrained('activity_calendars')->nullOnDelete()->cascadeOnUpdate();
            }
            if (! Schema::hasColumn('activity_proposals', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('calendar_id')->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            }
            if (! Schema::hasColumn('activity_proposals', 'proposal_status')) {
                $table->enum('proposal_status', ['DRAFT', 'PENDING', 'UNDER_REVIEW', 'APPROVED', 'REJECTED', 'REVISION'])->default('PENDING');
            }
            if (! Schema::hasColumn('activity_proposals', 'form_organization_name')) {
                $table->string('form_organization_name', 255)->nullable();
            }
            if (! Schema::hasColumn('activity_proposals', 'organization_logo_path')) {
                $table->string('organization_logo_path', 500)->nullable();
            }
            if (! Schema::hasColumn('activity_proposals', 'school_code')) {
                $table->string('school_code', 32)->nullable();
            }
            if (! Schema::hasColumn('activity_proposals', 'department_program')) {
                $table->string('department_program', 255)->nullable();
            }
            if (! Schema::hasColumn('activity_proposals', 'academic_year')) {
                $table->string('academic_year', 50)->nullable();
            }
            if (! Schema::hasColumn('activity_proposals', 'proposed_time')) {
                $table->string('proposed_time', 32)->nullable();
            }
            if (! Schema::hasColumn('activity_proposals', 'budget_materials_supplies')) {
                $table->decimal('budget_materials_supplies', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('activity_proposals', 'budget_food_beverage')) {
                $table->decimal('budget_food_beverage', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('activity_proposals', 'budget_other_expenses')) {
                $table->decimal('budget_other_expenses', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('activity_proposals', 'budget_breakdown_items')) {
                $table->json('budget_breakdown_items')->nullable();
            }
            if (! Schema::hasColumn('activity_proposals', 'resume_resource_persons_path')) {
                $table->string('resume_resource_persons_path', 500)->nullable();
            }
            if (! Schema::hasColumn('activity_proposals', 'external_funding_support_path')) {
                $table->string('external_funding_support_path', 512)->nullable();
            }
        });
    }
};

