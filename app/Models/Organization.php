<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'organization_name',
        'organization_type',
        'college_department',
        'purpose',
        'adviser_name',
        'founded_date',
        'organization_status',
    ];

    protected function casts(): array
    {
        return [
            'founded_date' => 'date',
        ];
    }

    public function officers(): HasMany
    {
        return $this->hasMany(OrganizationOfficer::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(OrganizationRegistration::class);
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(OrganizationRenewal::class);
    }

    public function activityCalendars(): HasMany
    {
        return $this->hasMany(ActivityCalendar::class);
    }

    public function activityProposals(): HasMany
    {
        return $this->hasMany(ActivityProposal::class);
    }

    public function activityReports(): HasMany
    {
        return $this->hasMany(ActivityReport::class);
    }

    public function communicationThreads(): HasMany
    {
        return $this->hasMany(CommunicationThread::class);
    }
}
