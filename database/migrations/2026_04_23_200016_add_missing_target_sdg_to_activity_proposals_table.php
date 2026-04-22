<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_proposals')) {
            return;
        }

        Schema::table('activity_proposals', function (Blueprint $table): void {
            if (! Schema::hasColumn('activity_proposals', 'target_sdg')) {
                $table->string('target_sdg', 64)
                    ->nullable()
                    ->after('program_flow');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('activity_proposals')) {
            return;
        }

        Schema::table('activity_proposals', function (Blueprint $table): void {
            if (Schema::hasColumn('activity_proposals', 'target_sdg')) {
                $table->dropColumn('target_sdg');
            }
        });
    }
};

