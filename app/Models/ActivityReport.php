<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityReport extends Model
{
    protected $fillable = [
        'proposal_id',
        'organization_id',
        'user_id',
        'report_submission_date',
        'report_file',
        'accomplishment_summary',
        'report_status',
        'activity_event_title',
        'school_code',
        'department',
        'poster_image_path',
        'event_name',
        'event_starts_at',
        'activity_chairs',
        'prepared_by',
        'program_content',
        'supporting_photo_paths',
        'certificate_sample_path',
        'evaluation_report',
        'participants_reached_percent',
        'evaluation_form_sample_path',
        'attendance_sheet_path',
    ];

    protected function casts(): array
    {
        return [
            'report_submission_date' => 'date',
            'event_starts_at' => 'datetime',
            'supporting_photo_paths' => 'array',
            'participants_reached_percent' => 'decimal:2',
        ];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(ActivityProposal::class, 'proposal_id');
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
