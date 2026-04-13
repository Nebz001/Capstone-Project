<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_request_forms', function (Blueprint $table) {
            $table->foreignId('activity_calendar_entry_id')
                ->nullable()
                ->after('user_id')
                ->constrained('activity_calendar_entries')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('activity_request_forms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('activity_calendar_entry_id');
        });
    }
};
