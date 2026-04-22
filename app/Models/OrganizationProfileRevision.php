<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationProfileRevision extends Model
{
    protected $fillable = [
        'organization_id',
        'requested_by',
        'revision_notes',
        'status',
        'addressed_at',
    ];

    protected function casts(): array
    {
        return [
            'addressed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
