<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ActivityCalendarEntry extends Model
{
    protected $fillable = [
        'activity_calendar_id',
        'activity_date',
        'activity_name',
        'target_sdg',
        'venue',
        'target_participants',
        'target_program',
        'estimated_budget',
    ];

    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
            'estimated_budget' => 'decimal:2',
        ];
    }

    public function activityCalendar(): BelongsTo
    {
        return $this->belongsTo(ActivityCalendar::class);
    }

    public function proposal(): HasOne
    {
        return $this->hasOne(ActivityProposal::class, 'activity_calendar_entry_id');
    }
}
