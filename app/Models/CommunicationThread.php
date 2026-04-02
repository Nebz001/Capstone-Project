<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationThread extends Model
{
    protected $fillable = [
        'organization_id',
        'proposal_id',
        'thread_subject',
        'thread_type',
        'thread_status',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(ActivityProposal::class, 'proposal_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CommunicationMessage::class, 'thread_id');
    }
}
