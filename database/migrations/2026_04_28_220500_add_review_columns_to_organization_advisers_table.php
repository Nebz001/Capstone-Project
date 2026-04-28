<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_advisers', function (Blueprint $table): void {
            if (! Schema::hasColumn('organization_advisers', 'status')) {
                $table->string('status', 20)
                    ->default('pending')
                    ->comment('pending, approved, rejected')
                    ->after('relieved_at');
            }
            if (! Schema::hasColumn('organization_advisers', 'rejection_notes')) {
                $table->text('rejection_notes')->nullable()->after('status');
            }
            if (! Schema::hasColumn('organization_advisers', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('rejection_notes')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('organization_advisers', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
            if (! Schema::hasColumn('organization_advisers', 'submission_id')) {
                $table->foreignId('submission_id')->nullable()->after('reviewed_at')->constrained('organization_submissions')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('organization_advisers', function (Blueprint $table): void {
            if (Schema::hasColumn('organization_advisers', 'submission_id')) {
                $table->dropConstrainedForeignId('submission_id');
            }
            if (Schema::hasColumn('organization_advisers', 'reviewed_by')) {
                $table->dropConstrainedForeignId('reviewed_by');
            }
            if (Schema::hasColumn('organization_advisers', 'reviewed_at')) {
                $table->dropColumn('reviewed_at');
            }
            if (Schema::hasColumn('organization_advisers', 'rejection_notes')) {
                $table->dropColumn('rejection_notes');
            }
            if (Schema::hasColumn('organization_advisers', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};

