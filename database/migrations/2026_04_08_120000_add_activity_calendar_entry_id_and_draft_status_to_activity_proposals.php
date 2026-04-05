<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->foreignId('activity_calendar_entry_id')
                ->nullable()
                ->after('calendar_id')
                ->constrained('activity_calendar_entries')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->unique('activity_calendar_entry_id');
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE activity_proposals MODIFY COLUMN proposal_status ENUM('DRAFT','PENDING','UNDER_REVIEW','APPROVED','REJECTED','REVISION') NOT NULL DEFAULT 'PENDING'");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE activity_proposals MODIFY COLUMN proposal_status ENUM('PENDING','UNDER_REVIEW','APPROVED','REJECTED','REVISION') NOT NULL DEFAULT 'PENDING'");
        }

        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->dropForeign(['activity_calendar_entry_id']);
            $table->dropUnique(['activity_calendar_entry_id']);
            $table->dropColumn('activity_calendar_entry_id');
        });
    }
};
