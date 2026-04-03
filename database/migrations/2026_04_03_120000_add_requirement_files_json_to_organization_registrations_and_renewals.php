<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('organization_registrations', 'requirement_files')) {
            Schema::table('organization_registrations', function (Blueprint $table) {
                $table->json('requirement_files')->nullable()->after('req_others_specify');
            });
        }

        if (! Schema::hasColumn('organization_renewals', 'requirement_files')) {
            Schema::table('organization_renewals', function (Blueprint $table) {
                $table->json('requirement_files')->nullable()->after('req_others_specify');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('organization_registrations', 'requirement_files')) {
            Schema::table('organization_registrations', function (Blueprint $table) {
                $table->dropColumn('requirement_files');
            });
        }

        if (Schema::hasColumn('organization_renewals', 'requirement_files')) {
            Schema::table('organization_renewals', function (Blueprint $table) {
                $table->dropColumn('requirement_files');
            });
        }
    }
};
