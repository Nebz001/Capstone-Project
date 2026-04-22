<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicTerm extends Model
{
    protected $fillable = [
        'academic_year',
        'semester',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
        'is_active' => 'boolean',
    ];

    public function organizationSubmissions(): HasMany
    {
        return $this->hasMany(OrganizationSubmission::class);
    }

    public function activityCalendars(): HasMany
    {
        return $this->hasMany(ActivityCalendar::class);
    }

    public function activityProposals(): HasMany
    {
        return $this->hasMany(ActivityProposal::class);
    }
}
