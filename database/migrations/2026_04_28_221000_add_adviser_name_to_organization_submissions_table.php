<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_submissions', function (Blueprint $table): void {
            if (! Schema::hasColumn('organization_submissions', 'adviser_name')) {
                $table->string('adviser_name', 100)->nullable()->after('contact_person');
            }
        });
    }

    public function down(): void
    {
        Schema::table('organization_submissions', function (Blueprint $table): void {
            if (Schema::hasColumn('organization_submissions', 'adviser_name')) {
                $table->dropColumn('adviser_name');
            }
        });
    }
};

