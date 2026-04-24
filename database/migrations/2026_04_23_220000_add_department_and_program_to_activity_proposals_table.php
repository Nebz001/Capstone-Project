<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable('activity_proposals')) {
      return;
    }

    Schema::table('activity_proposals', function (Blueprint $table): void {
      if (! Schema::hasColumn('activity_proposals', 'school_code')) {
        $table->string('school_code', 32)->nullable()->after('academic_term_id');
      }
      if (! Schema::hasColumn('activity_proposals', 'program')) {
        $table->string('program', 255)->nullable()->after('school_code');
      }
    });

    if (Schema::hasTable('organizations')) {
      if (Schema::hasColumn('organizations', 'college_department')) {
        DB::statement("
                    UPDATE activity_proposals ap
                    SET program = COALESCE(NULLIF(ap.program, ''), NULLIF(o.college_department, ''))
                    FROM organizations o
                    WHERE o.id = ap.organization_id
                      AND (ap.program IS NULL OR ap.program = '')
                ");
      }

      $schoolSourceColumn = null;
      if (Schema::hasColumn('organizations', 'college_school')) {
        $schoolSourceColumn = 'college_school';
      } elseif (Schema::hasColumn('organizations', 'school_code')) {
        $schoolSourceColumn = 'school_code';
      }

      if ($schoolSourceColumn !== null) {
        DB::statement("
                    UPDATE activity_proposals ap
                    SET school_code = COALESCE(NULLIF(ap.school_code, ''), NULLIF(o.{$schoolSourceColumn}, ''))
                    FROM organizations o
                    WHERE o.id = ap.organization_id
                      AND (ap.school_code IS NULL OR ap.school_code = '')
                ");
      }
    }
  }

  public function down(): void
  {
    if (! Schema::hasTable('activity_proposals')) {
      return;
    }

    Schema::table('activity_proposals', function (Blueprint $table): void {
      if (Schema::hasColumn('activity_proposals', 'program')) {
        $table->dropColumn('program');
      }
      if (Schema::hasColumn('activity_proposals', 'school_code')) {
        $table->dropColumn('school_code');
      }
    });
  }
};
