<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationAdviser extends Model
{
    protected $fillable = [
        'organization_id',
        'user_id',
        'assigned_at',
        'relieved_at',
        'status',
        'rejection_notes',
        'reviewed_by',
        'reviewed_at',
        'submission_id',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'relieved_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(OrganizationSubmission::class, 'submission_id');
    }

    public function scopeActive($query)
    {
        return $query
            ->where(function ($statusQuery): void {
                $statusQuery->whereRaw('LOWER(status) = ?', ['approved'])
                    ->orWhereRaw('LOWER(status) = ?', ['active']);
            })
            ->whereNull('relieved_at');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
