<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_request_forms')) {
            return;
        }

        Schema::table('activity_request_forms', function (Blueprint $table): void {
            if (! Schema::hasColumn('activity_request_forms', 'request_letter_has_rationale')) {
                $table->boolean('request_letter_has_rationale')->default(false)->after('venue');
            }
            if (! Schema::hasColumn('activity_request_forms', 'request_letter_has_objectives')) {
                $table->boolean('request_letter_has_objectives')->default(false)->after('request_letter_has_rationale');
            }
            if (! Schema::hasColumn('activity_request_forms', 'request_letter_has_program')) {
                $table->boolean('request_letter_has_program')->default(false)->after('request_letter_has_objectives');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('activity_request_forms')) {
            return;
        }

        Schema::table('activity_request_forms', function (Blueprint $table): void {
            foreach ([
                'request_letter_has_program',
                'request_letter_has_objectives',
                'request_letter_has_rationale',
            ] as $column) {
                if (Schema::hasColumn('activity_request_forms', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

