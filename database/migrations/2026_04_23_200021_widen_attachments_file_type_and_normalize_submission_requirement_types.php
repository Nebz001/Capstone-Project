<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `attachments` MODIFY `file_type` VARCHAR(120) NOT NULL');
        }

        $this->replaceFileTypePrefix('registration_requirement_file:', 'registration_requirement:');
        $this->replaceFileTypePrefix('renewal_requirement_file:', 'renewal_requirement:');
    }

    public function down(): void
    {
        $this->replaceFileTypePrefix('registration_requirement:', 'registration_requirement_file:');
        $this->replaceFileTypePrefix('renewal_requirement:', 'renewal_requirement_file:');

        // Intentionally do not narrow `file_type` to VARCHAR(60): restored strings can exceed 60 with long requirement keys.
    }

    private function replaceFileTypePrefix(string $oldPrefix, string $newPrefix): void
    {
        $oldLen = Str::length($oldPrefix);

        DB::table('attachments')
            ->where('file_type', 'like', $oldPrefix.'%')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($oldPrefix, $newPrefix, $oldLen): void {
                foreach ($rows as $row) {
                    $ft = (string) $row->file_type;
                    if (! str_starts_with($ft, $oldPrefix)) {
                        continue;
                    }
                    $suffix = Str::substr($ft, $oldLen);
                    DB::table('attachments')->where('id', $row->id)->update([
                        'file_type' => $newPrefix.$suffix,
                    ]);
                }
            });
    }
};
