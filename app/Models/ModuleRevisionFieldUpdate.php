<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Polymorphic field update audit row.
 *
 * Mirrors `OrganizationRevisionFieldUpdate` but is morphed onto any reviewable
 * record (ActivityCalendar / ActivityProposal / ActivityReport). Each row
 * captures one (section_key, field_key) pair that the officer just resubmitted
 * after the admin flagged it for revision, plus the old/new value or file
 * metadata so the admin "Updated" badge / diff can render on the review page.
 *
 * Acknowledgement is auto-applied by the admin's Save Review when the
 * matching field review status leaves the `pending` state — same pattern as
 * the registration flow.
 */
class ModuleRevisionFieldUpdate extends Model
{
    protected $fillable = [
        'reviewable_type',
        'reviewable_id',
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
            'reviewable_id' => 'integer',
            'resubmitted_by' => 'integer',
            'acknowledged_by' => 'integer',
        ];
    }

    public function reviewable(): MorphTo
    {
        return $this->morphTo();
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
