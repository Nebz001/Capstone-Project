<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'role_type')) {
                $table->dropColumn('role_type');
            }
            if (Schema::hasColumn('users', 'account_field_reviews')) {
                $table->dropColumn('account_field_reviews');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'role_type')) {
                $table->enum('role_type', ['ORG_OFFICER', 'APPROVER', 'ADMIN'])->nullable()->after('password');
            }
            if (! Schema::hasColumn('users', 'account_field_reviews')) {
                $table->json('account_field_reviews')->nullable()->after('officer_validation_notes');
            }
        });
    }
};

