<?php

namespace App\Domain\Intake\Presenters;

use App\Domain\Intake\Models\IncomingUpload;

final class IncomingUploadPresenter
{
    /** @return array<string,mixed> */
    public function present(IncomingUpload $u): array
    {
        $sha = $u->sha256;

        return [
            'id' => $u->id,
            'upload_id' => $u->upload_id,
            'original_filename' => $u->original_filename,
            'sanitized_filename' => basename((string) $u->incoming_path),
            'mime_type' => $u->mime_type,
            'extension' => $u->extension,
            'size_bytes' => $u->file_size_bytes,
            'dimensions' => $u->width.' x '.$u->height,
            'logical_disk' => 'archive_quarantine',
            'incoming_path' => $u->incoming_path,
            'processing_status' => $u->processing_status->value,
            'review_status' => $u->review_status->value,
            'duplicate_status' => $u->duplicate_status->value,
            'sha256' => $sha,
            'short_sha256' => is_string($sha) ? substr($sha, 0, 16).'...' : null,
            'perceptual_hash' => $u->perceptual_hash,
            'media_item_id' => $u->media_item_id,
            'source_file_retained' => $u->source_file_retained,
            'retained_at' => $u->retained_at,
            'integrity_status' => $u->source_file_retained && is_string($sha) ? 'verified' : 'not_retained',
            'submitted_at' => $u->submitted_at,
        ];
    }
}
