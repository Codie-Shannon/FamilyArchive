<?php

namespace App\Domain\Access\Models;

use App\Domain\Intake\Models\IncomingUpload;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'submission_id', 'user_id', 'upload_session_id', 'incoming_upload_id', 'status',
    'original_name', 'source_context', 'proposed_metadata', 'automation_preferences',
    'reviewed_by', 'reviewer_note', 'reviewed_at',
])]
final class ContributorSubmission extends Model
{
    protected function casts(): array
    {
        return [
            'proposed_metadata' => 'array',
            'automation_preferences' => 'array',
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<UploadSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(UploadSession::class, 'upload_session_id');
    }

    /** @return BelongsTo<IncomingUpload, $this> */
    public function incomingUpload(): BelongsTo
    {
        return $this->belongsTo(IncomingUpload::class);
    }

    /** @return BelongsTo<User, $this> */
    public function contributor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
