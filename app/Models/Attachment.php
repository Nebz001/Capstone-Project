<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends Model
{
    public const TYPE_REGISTRATION_REQUIREMENT = 'registration_requirement';

    public const TYPE_RENEWAL_REQUIREMENT = 'renewal_requirement';

    public const TYPE_PROPOSAL_LOGO = 'proposal_logo';

    public const TYPE_PROPOSAL_EXTERNAL_FUNDING = 'proposal_external_funding';

    public const TYPE_PROPOSAL_RESOURCE_RESUME = 'proposal_resource_resume';

    public const TYPE_REQUEST_LETTER = 'request_letter';

    public const TYPE_REQUEST_SPEAKER_RESUME = 'request_speaker_resume';

    public const TYPE_REQUEST_POST_SURVEY = 'request_post_survey';

    public const TYPE_REPORT_POSTER = 'report_poster';

    public const TYPE_REPORT_SUPPORTING_PHOTO = 'report_supporting_photo';

    public const TYPE_REPORT_CERTIFICATE = 'report_certificate';

    public const TYPE_REPORT_EVALUATION_FORM = 'report_evaluation_form';

    public const TYPE_REPORT_ATTENDANCE = 'report_attendance';

    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'uploaded_by',
        'file_type',
        'original_name',
        'stored_path',
        'mime_type',
        'file_size_kb',
    ];

    protected function casts(): array
    {
        return [
            'file_size_kb' => 'integer',
        ];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public static function fileTypeForSubmissionRequirement(string $submissionType): string
    {
        return $submissionType === OrganizationSubmission::TYPE_RENEWAL
            ? self::TYPE_RENEWAL_REQUIREMENT
            : self::TYPE_REGISTRATION_REQUIREMENT;
    }

    public static function fileTypeForSubmissionRequirementKey(string $submissionType, string $requirementKey): string
    {
        return self::fileTypeForSubmissionRequirement($submissionType).':'.$requirementKey;
    }
}
