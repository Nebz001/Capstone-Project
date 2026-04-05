<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_calendar_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_calendar_id')
                ->constrained('activity_calendars')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->date('activity_date');
            $table->string('activity_name', 500);
            $table->string('sdg', 64);
            $table->string('venue', 255);
            $table->text('participant_program');
            $table->string('budget', 255);
            $table->timestamps();
        });

        Schema::table('activity_calendars', function (Blueprint $table) {
            $table->string('submitted_organization_name', 255)->nullable()->after('organization_id');
        });
    }

    public function down(): void
    {
        Schema::table('activity_calendars', function (Blueprint $table) {
            $table->dropColumn('submitted_organization_name');
        });

        Schema::dropIfExists('activity_calendar_entries');
    }
};
