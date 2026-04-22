<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notification extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'notifiable_type',
        'notifiable_id',
        'type',
        'title',
        'body',
        'read_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'notifiable_id' => 'integer',
            'read_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}
