<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityCalendarEntry extends Model
{
    protected $fillable = [
        'activity_calendar_id',
        'activity_date',
        'activity_name',
        'sdg',
        'venue',
        'participant_program',
        'budget',
    ];

    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
        ];
    }

    public function activityCalendar(): BelongsTo
    {
        return $this->belongsTo(ActivityCalendar::class);
    }
}
