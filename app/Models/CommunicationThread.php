<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CommunicationThread extends Model
{
    protected $fillable = [
        'organization_id',
        'subject_type',
        'subject_id',
        'thread_subject',
        'status',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CommunicationMessage::class, 'thread_id');
    }
}
