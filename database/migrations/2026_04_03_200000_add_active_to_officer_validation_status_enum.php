<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement(
                "ALTER TABLE users MODIFY officer_validation_status ENUM('PENDING','APPROVED','ACTIVE','REJECTED','REVISION_REQUIRED') NOT NULL DEFAULT 'PENDING'"
            );

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_officer_validation_status_check');
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_officer_validation_status_check CHECK (officer_validation_status IN ('PENDING','APPROVED','ACTIVE','REJECTED','REVISION_REQUIRED'))");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement(
                "ALTER TABLE users MODIFY officer_validation_status ENUM('PENDING','APPROVED','REJECTED','REVISION_REQUIRED') NOT NULL DEFAULT 'PENDING'"
            );

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_officer_validation_status_check');
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_officer_validation_status_check CHECK (officer_validation_status IN ('PENDING','APPROVED','REJECTED','REVISION_REQUIRED'))");
        }
    }
};
