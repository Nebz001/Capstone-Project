<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_registrations', function (Blueprint $table) {
            $table->json('section_review_state')->nullable()->after('revision_comment_requirements');
        });
    }

    public function down(): void
    {
        Schema::table('organization_registrations', function (Blueprint $table) {
            $table->dropColumn('section_review_state');
        });
    }
};
