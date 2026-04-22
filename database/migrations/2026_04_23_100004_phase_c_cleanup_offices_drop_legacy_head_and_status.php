<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('offices')) {
            return;
        }

        Schema::table('offices', function (Blueprint $table): void {
            if (! Schema::hasColumn('offices', 'head_user_id')) {
                $table->foreignId('head_user_id')->nullable()->after('office_name')->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            }
            if (! Schema::hasColumn('offices', 'status')) {
                $table->enum('status', ['active', 'inactive'])->default('active')->after('office_status');
            }
        });

        if (Schema::hasColumn('offices', 'office_status')) {
            DB::table('offices')->update([
                'status' => DB::raw("CASE office_status WHEN 'INACTIVE' THEN 'inactive' ELSE 'active' END"),
            ]);
        }

        Schema::table('offices', function (Blueprint $table): void {
            if (Schema::hasColumn('offices', 'office_head')) {
                $table->dropColumn('office_head');
            }
            if (Schema::hasColumn('offices', 'office_status')) {
                $table->dropColumn('office_status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('offices')) {
            return;
        }

        Schema::table('offices', function (Blueprint $table): void {
            if (! Schema::hasColumn('offices', 'office_head')) {
                $table->string('office_head', 150)->nullable()->after('office_name');
            }
            if (! Schema::hasColumn('offices', 'office_status')) {
                $table->enum('office_status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE')->after('office_email');
            }
        });
    }
};

