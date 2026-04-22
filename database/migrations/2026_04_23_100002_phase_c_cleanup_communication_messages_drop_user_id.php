<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('communication_messages')) {
            return;
        }

        Schema::table('communication_messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('communication_messages', 'sent_by')) {
                $table->foreignId('sent_by')->nullable()->after('thread_id')->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            }
        });

        if (Schema::hasColumn('communication_messages', 'user_id')) {
            DB::table('communication_messages')->whereNull('sent_by')->update(['sent_by' => DB::raw('user_id')]);
        }

        Schema::table('communication_messages', function (Blueprint $table): void {
            if (Schema::hasColumn('communication_messages', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('communication_messages')) {
            return;
        }

        Schema::table('communication_messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('communication_messages', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('thread_id')->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            }
        });
    }
};

