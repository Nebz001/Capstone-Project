<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organizations')) {
            return;
        }

        Schema::table('organizations', function (Blueprint $table): void {
            if (! Schema::hasColumn('organizations', 'acronym')) {
                $table->string('acronym', 20)->nullable()->after('organization_name');
            }

            if (! Schema::hasColumn('organizations', 'logo_path')) {
                $table->string('logo_path', 500)->nullable()->after('adviser_name');
            }

            if (! Schema::hasColumn('organizations', 'is_profile_locked')) {
                $table->boolean('is_profile_locked')
                    ->default(false)
                    ->after('profile_revision_notes');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('organizations')) {
            return;
        }

        Schema::table('organizations', function (Blueprint $table): void {
            if (Schema::hasColumn('organizations', 'is_profile_locked')) {
                $table->dropColumn('is_profile_locked');
            }

            if (Schema::hasColumn('organizations', 'logo_path')) {
                $table->dropColumn('logo_path');
            }

            if (Schema::hasColumn('organizations', 'acronym')) {
                $table->dropColumn('acronym');
            }
        });
    }
};
