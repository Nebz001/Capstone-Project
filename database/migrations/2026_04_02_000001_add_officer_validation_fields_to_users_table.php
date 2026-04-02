<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('officer_validation_status', ['PENDING', 'APPROVED', 'REJECTED', 'REVISION_REQUIRED'])
                ->default('PENDING')
                ->after('account_status');
            $table->text('officer_validation_notes')->nullable()->after('officer_validation_status');
            $table->timestamp('officer_validated_at')->nullable()->after('officer_validation_notes');
            $table->unsignedBigInteger('officer_validated_by')->nullable()->after('officer_validated_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'officer_validation_status',
                'officer_validation_notes',
                'officer_validated_at',
                'officer_validated_by',
            ]);
        });
    }
};

