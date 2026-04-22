<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationMessage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'thread_id',
        'sent_by',
        'message_content',
        'sent_at',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'is_read' => 'boolean',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(CommunicationThread::class, 'thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
