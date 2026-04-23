<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_request_forms', function (Blueprint $table): void {
            $table->string('target_sdg', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::table('activity_request_forms', function (Blueprint $table): void {
            $table->string('target_sdg', 64)->change();
        });
    }
};
