<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->string('form_organization_name', 255)->nullable()->after('user_id');
            $table->string('organization_logo_path', 500)->nullable()->after('form_organization_name');
            $table->string('school_code', 32)->nullable()->after('organization_logo_path');
            $table->string('department_program', 255)->nullable()->after('school_code');
            $table->string('academic_year', 50)->nullable()->after('department_program');
            $table->string('proposed_time', 32)->nullable()->after('proposed_end_date');
            $table->text('overall_goal')->nullable()->after('venue');
            $table->text('specific_objectives')->nullable()->after('overall_goal');
            $table->text('criteria_mechanics')->nullable()->after('specific_objectives');
            $table->text('program_flow')->nullable()->after('criteria_mechanics');
            $table->string('source_of_funding', 255)->nullable()->after('estimated_budget');
            $table->decimal('budget_materials_supplies', 12, 2)->nullable()->after('source_of_funding');
            $table->decimal('budget_food_beverage', 12, 2)->nullable()->after('budget_materials_supplies');
            $table->decimal('budget_other_expenses', 12, 2)->nullable()->after('budget_food_beverage');
            $table->string('resume_resource_persons_path', 500)->nullable()->after('budget_other_expenses');
        });
    }

    public function down(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->dropColumn([
                'form_organization_name',
                'organization_logo_path',
                'school_code',
                'department_program',
                'academic_year',
                'proposed_time',
                'overall_goal',
                'specific_objectives',
                'criteria_mechanics',
                'program_flow',
                'source_of_funding',
                'budget_materials_supplies',
                'budget_food_beverage',
                'budget_other_expenses',
                'resume_resource_persons_path',
            ]);
        });
    }
};
