<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivityCalendar extends Model
{
    protected $fillable = [
        'organization_id',
        'academic_year',
        'semester',
        'calendar_file',
        'submission_date',
        'calendar_status',
    ];

    protected function casts(): array
    {
        return [
            'submission_date' => 'date',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function activityProposals(): HasMany
    {
        return $this->hasMany(ActivityProposal::class, 'calendar_id');
    }
}
