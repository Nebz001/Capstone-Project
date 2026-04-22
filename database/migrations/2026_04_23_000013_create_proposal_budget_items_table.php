<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_budget_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_proposal_id')->constrained('activity_proposals')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('category', 100);
            $table->string('item_description', 255);
            $table->unsignedSmallInteger('quantity')->nullable();
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('total_cost', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_budget_items');
    }
};
