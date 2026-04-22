<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_calendars')) {
            return;
        }

        Schema::table('activity_calendars', function (Blueprint $table): void {
            if (! Schema::hasColumn('activity_calendars', 'submitted_by')) {
                $table->foreignId('submitted_by')
                    ->nullable()
                    ->after('organization_id')
                    ->constrained('users')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('activity_calendars')) {
            return;
        }

        Schema::table('activity_calendars', function (Blueprint $table): void {
            if (Schema::hasColumn('activity_calendars', 'submitted_by')) {
                $table->dropConstrainedForeignId('submitted_by');
            }
        });
    }
};

