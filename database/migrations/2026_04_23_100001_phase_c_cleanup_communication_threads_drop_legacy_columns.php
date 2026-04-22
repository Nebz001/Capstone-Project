<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('communication_threads')) {
            return;
        }

        Schema::table('communication_threads', function (Blueprint $table): void {
            if (! Schema::hasColumn('communication_threads', 'subject_type')) {
                $table->string('subject_type', 191)->nullable()->after('organization_id');
            }
            if (! Schema::hasColumn('communication_threads', 'subject_id')) {
                $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
            }
            if (! Schema::hasColumn('communication_threads', 'status')) {
                $table->enum('status', ['open', 'closed'])->default('open')->after('thread_status');
            }
        });

        if (Schema::hasColumn('communication_threads', 'thread_status')) {
            DB::table('communication_threads')->update([
                'status' => DB::raw("CASE thread_status WHEN 'CLOSED' THEN 'closed' ELSE 'open' END"),
            ]);
        }

        if (Schema::hasColumn('communication_threads', 'proposal_id')) {
            DB::table('communication_threads')
                ->whereNull('subject_type')
                ->whereNotNull('proposal_id')
                ->update([
                    'subject_type' => 'App\\Models\\ActivityProposal',
                    'subject_id' => DB::raw('proposal_id'),
                ]);
        }

        Schema::table('communication_threads', function (Blueprint $table): void {
            if (Schema::hasColumn('communication_threads', 'proposal_id')) {
                $table->dropConstrainedForeignId('proposal_id');
            }
            if (Schema::hasColumn('communication_threads', 'thread_type')) {
                $table->dropColumn('thread_type');
            }
            if (Schema::hasColumn('communication_threads', 'thread_status')) {
                $table->dropColumn('thread_status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('communication_threads')) {
            return;
        }

        Schema::table('communication_threads', function (Blueprint $table): void {
            if (! Schema::hasColumn('communication_threads', 'proposal_id')) {
                $table->foreignId('proposal_id')->nullable()->after('organization_id')->constrained('activity_proposals')->nullOnDelete()->cascadeOnUpdate();
            }
            if (! Schema::hasColumn('communication_threads', 'thread_type')) {
                $table->enum('thread_type', ['PROPOSAL', 'REGISTRATION', 'RENEWAL', 'REPORT'])->default('PROPOSAL')->after('thread_subject');
            }
            if (! Schema::hasColumn('communication_threads', 'thread_status')) {
                $table->enum('thread_status', ['OPEN', 'CLOSED'])->default('OPEN')->after('thread_type');
            }
        });
    }
};

