<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_submissions', function (Blueprint $table): void {
            if (! Schema::hasColumn('organization_submissions', 'registration_field_reviews')) {
                $table->json('registration_field_reviews')->nullable()->after('approval_decision');
            }
            if (! Schema::hasColumn('organization_submissions', 'registration_section_reviews')) {
                $table->json('registration_section_reviews')->nullable()->after('registration_field_reviews');
            }
        });
    }

    public function down(): void
    {
        Schema::table('organization_submissions', function (Blueprint $table): void {
            if (Schema::hasColumn('organization_submissions', 'registration_section_reviews')) {
                $table->dropColumn('registration_section_reviews');
            }
            if (Schema::hasColumn('organization_submissions', 'registration_field_reviews')) {
                $table->dropColumn('registration_field_reviews');
            }
        });
    }
};
