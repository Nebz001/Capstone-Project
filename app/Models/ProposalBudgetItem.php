<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalBudgetItem extends Model
{
    protected $fillable = [
        'activity_proposal_id',
        'category',
        'item_description',
        'quantity',
        'unit_cost',
        'total_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
        ];
    }

    public function activityProposal(): BelongsTo
    {
        return $this->belongsTo(ActivityProposal::class);
    }
}
