<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('approval_workflows');
    }

    public function down(): void
    {
        // Legacy table recreation is intentionally omitted because
        // approval state is now normalized in approval_workflow_steps + approval_logs.
    }
};

