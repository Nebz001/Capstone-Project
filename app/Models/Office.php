<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Office extends Model
{
    protected $fillable = [
        'office_name',
        'head_user_id',
        'office_email',
        'status',
    ];

    public function headUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }
}
