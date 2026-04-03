<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('organizations', 'profile_information_revision_requested')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->boolean('profile_information_revision_requested')
                    ->default(false)
                    ->after('organization_status');
                $table->text('profile_revision_notes')->nullable()->after('profile_information_revision_requested');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('organizations', 'profile_information_revision_requested')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropColumn([
                    'profile_information_revision_requested',
                    'profile_revision_notes',
                ]);
            });
        }
    }
};
