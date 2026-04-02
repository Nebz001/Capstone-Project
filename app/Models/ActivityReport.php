<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityReport extends Model
{
    protected $fillable = [
        'proposal_id',
        'organization_id',
        'user_id',
        'report_submission_date',
        'report_file',
        'accomplishment_summary',
        'report_status',
    ];

    protected function casts(): array
    {
        return [
            'report_submission_date' => 'date',
        ];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(ActivityProposal::class, 'proposal_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
