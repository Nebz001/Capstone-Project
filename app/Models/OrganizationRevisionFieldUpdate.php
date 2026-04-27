<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationRevisionFieldUpdate extends Model
{
    protected $fillable = [
        'organization_submission_id',
        'section_key',
        'field_key',
        'old_value',
        'new_value',
        'old_file_meta',
        'new_file_meta',
        'resubmitted_at',
        'resubmitted_by',
        'acknowledged_at',
        'acknowledged_by',
    ];

    protected function casts(): array
    {
        return [
            'old_file_meta' => 'array',
            'new_file_meta' => 'array',
            'resubmitted_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'organization_submission_id' => 'integer',
            'resubmitted_by' => 'integer',
            'acknowledged_by' => 'integer',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(OrganizationSubmission::class, 'organization_submission_id');
    }

    public function resubmittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resubmitted_by');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}

