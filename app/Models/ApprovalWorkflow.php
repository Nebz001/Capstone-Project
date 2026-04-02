<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalWorkflow extends Model
{
    protected $fillable = [
        'proposal_id',
        'office_id',
        'user_id',
        'approval_level',
        'current_step',
        'review_date',
        'acted_at',
        'decision_status',
        'review_comments',
    ];

    protected function casts(): array
    {
        return [
            'current_step' => 'boolean',
            'review_date' => 'date',
            'acted_at' => 'datetime',
        ];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(ActivityProposal::class, 'proposal_id');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
