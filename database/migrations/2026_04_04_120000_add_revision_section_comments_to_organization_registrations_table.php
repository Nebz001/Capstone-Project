<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_registrations', function (Blueprint $table) {
            $table->text('revision_comment_application')->nullable()->after('additional_remarks');
            $table->text('revision_comment_contact')->nullable()->after('revision_comment_application');
            $table->text('revision_comment_organizational')->nullable()->after('revision_comment_contact');
            $table->text('revision_comment_requirements')->nullable()->after('revision_comment_organizational');
        });
    }

    public function down(): void
    {
        Schema::table('organization_registrations', function (Blueprint $table) {
            $table->dropColumn([
                'revision_comment_application',
                'revision_comment_contact',
                'revision_comment_organizational',
                'revision_comment_requirements',
            ]);
        });
    }
};
