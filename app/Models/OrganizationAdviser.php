<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationAdviser extends Model
{
    protected $fillable = [
        'organization_id',
        'user_id',
        'assigned_at',
        'relieved_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'relieved_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
