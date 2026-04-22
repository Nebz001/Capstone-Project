<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organizations')) {
            return;
        }

        Schema::table('organizations', function (Blueprint $table): void {
            if (! Schema::hasColumn('organizations', 'status')) {
                $table->enum('status', ['active', 'inactive', 'pending', 'suspended'])->default('pending')->after('organization_status');
            }
        });

        if (Schema::hasColumn('organizations', 'organization_status')) {
            DB::table('organizations')->update([
                'status' => DB::raw(
                    "CASE organization_status
                        WHEN 'ACTIVE' THEN 'active'
                        WHEN 'INACTIVE' THEN 'inactive'
                        WHEN 'SUSPENDED' THEN 'suspended'
                        ELSE 'pending'
                    END"
                ),
            ]);
        }

        Schema::table('organizations', function (Blueprint $table): void {
            foreach (['adviser_name', 'profile_information_revision_requested', 'profile_revision_notes', 'organization_status'] as $column) {
                if (Schema::hasColumn('organizations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('organizations')) {
            return;
        }

        Schema::table('organizations', function (Blueprint $table): void {
            if (! Schema::hasColumn('organizations', 'adviser_name')) {
                $table->string('adviser_name', 100)->nullable();
            }
            if (! Schema::hasColumn('organizations', 'profile_information_revision_requested')) {
                $table->boolean('profile_information_revision_requested')->default(false);
            }
            if (! Schema::hasColumn('organizations', 'profile_revision_notes')) {
                $table->text('profile_revision_notes')->nullable();
            }
            if (! Schema::hasColumn('organizations', 'organization_status')) {
                $table->enum('organization_status', ['ACTIVE', 'INACTIVE', 'PENDING', 'SUSPENDED'])->default('PENDING');
            }
        });
    }
};

