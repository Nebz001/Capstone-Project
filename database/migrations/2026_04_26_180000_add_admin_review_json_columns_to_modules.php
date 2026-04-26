<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_submissions', function (Blueprint $table): void {
            if (! Schema::hasColumn('organization_submissions', 'renewal_field_reviews')) {
                $table->json('renewal_field_reviews')->nullable()->after('registration_section_reviews');
            }
            if (! Schema::hasColumn('organization_submissions', 'renewal_section_reviews')) {
                $table->json('renewal_section_reviews')->nullable()->after('renewal_field_reviews');
            }
        });

        Schema::table('activity_calendars', function (Blueprint $table): void {
            if (! Schema::hasColumn('activity_calendars', 'admin_field_reviews')) {
                $table->json('admin_field_reviews')->nullable()->after('current_approval_step');
            }
            if (! Schema::hasColumn('activity_calendars', 'admin_section_reviews')) {
                $table->json('admin_section_reviews')->nullable()->after('admin_field_reviews');
            }
            if (! Schema::hasColumn('activity_calendars', 'admin_review_remarks')) {
                $table->text('admin_review_remarks')->nullable()->after('admin_section_reviews');
            }
        });

        Schema::table('activity_proposals', function (Blueprint $table): void {
            if (! Schema::hasColumn('activity_proposals', 'admin_field_reviews')) {
                $table->json('admin_field_reviews')->nullable()->after('current_approval_step');
            }
            if (! Schema::hasColumn('activity_proposals', 'admin_section_reviews')) {
                $table->json('admin_section_reviews')->nullable()->after('admin_field_reviews');
            }
            if (! Schema::hasColumn('activity_proposals', 'admin_review_remarks')) {
                $table->text('admin_review_remarks')->nullable()->after('admin_section_reviews');
            }
        });

        Schema::table('activity_reports', function (Blueprint $table): void {
            if (! Schema::hasColumn('activity_reports', 'admin_field_reviews')) {
                $table->json('admin_field_reviews')->nullable()->after('current_approval_step');
            }
            if (! Schema::hasColumn('activity_reports', 'admin_section_reviews')) {
                $table->json('admin_section_reviews')->nullable()->after('admin_field_reviews');
            }
            if (! Schema::hasColumn('activity_reports', 'admin_review_remarks')) {
                $table->text('admin_review_remarks')->nullable()->after('admin_section_reviews');
            }
        });
    }

    public function down(): void
    {
        Schema::table('organization_submissions', function (Blueprint $table): void {
            if (Schema::hasColumn('organization_submissions', 'renewal_section_reviews')) {
                $table->dropColumn('renewal_section_reviews');
            }
            if (Schema::hasColumn('organization_submissions', 'renewal_field_reviews')) {
                $table->dropColumn('renewal_field_reviews');
            }
        });

        Schema::table('activity_calendars', function (Blueprint $table): void {
            if (Schema::hasColumn('activity_calendars', 'admin_review_remarks')) {
                $table->dropColumn('admin_review_remarks');
            }
            if (Schema::hasColumn('activity_calendars', 'admin_section_reviews')) {
                $table->dropColumn('admin_section_reviews');
            }
            if (Schema::hasColumn('activity_calendars', 'admin_field_reviews')) {
                $table->dropColumn('admin_field_reviews');
            }
        });

        Schema::table('activity_proposals', function (Blueprint $table): void {
            if (Schema::hasColumn('activity_proposals', 'admin_review_remarks')) {
                $table->dropColumn('admin_review_remarks');
            }
            if (Schema::hasColumn('activity_proposals', 'admin_section_reviews')) {
                $table->dropColumn('admin_section_reviews');
            }
            if (Schema::hasColumn('activity_proposals', 'admin_field_reviews')) {
                $table->dropColumn('admin_field_reviews');
            }
        });

        Schema::table('activity_reports', function (Blueprint $table): void {
            if (Schema::hasColumn('activity_reports', 'admin_review_remarks')) {
                $table->dropColumn('admin_review_remarks');
            }
            if (Schema::hasColumn('activity_reports', 'admin_section_reviews')) {
                $table->dropColumn('admin_section_reviews');
            }
            if (Schema::hasColumn('activity_reports', 'admin_field_reviews')) {
                $table->dropColumn('admin_field_reviews');
            }
        });
    }
};
