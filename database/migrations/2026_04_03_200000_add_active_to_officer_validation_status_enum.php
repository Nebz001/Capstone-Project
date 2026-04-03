<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE users MODIFY officer_validation_status ENUM('PENDING','APPROVED','ACTIVE','REJECTED','REVISION_REQUIRED') NOT NULL DEFAULT 'PENDING'"
        );
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE users MODIFY officer_validation_status ENUM('PENDING','APPROVED','REJECTED','REVISION_REQUIRED') NOT NULL DEFAULT 'PENDING'"
        );
    }
};
