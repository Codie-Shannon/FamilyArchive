<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Metadata\Actions\UpdatePhotoMetadata;
use App\Domain\Metadata\Exceptions\NoEffectiveMetadataChange;
use App\Domain\Metadata\Exceptions\StaleMetadataRevision;
use App\Http\Controllers\Controller;
use App\Http\Requests\Archive\EditPhotoMetadataRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class PhotoMetadataController extends Controller
{
    public function edit(MediaItem $mediaItem): View
    {
        $this->assertEligible($mediaItem);

        return view('archive.metadata-edit', [
            'mediaItem' => $mediaItem,
        ]);
    }

    public function update(
        EditPhotoMetadataRequest $request,
        MediaItem $mediaItem,
        UpdatePhotoMetadata $action
    ): RedirectResponse {
        $this->assertEligible($mediaItem);

        try {
            $updated = $action->handle(
                $mediaItem,
                $request->user(),
                $request->normalized()
            );
        } catch (StaleMetadataRevision $exception) {
            return back()
                ->withErrors([
                    'expected_metadata_revision' => $exception->getMessage(),
                ])
                ->withInput();
        } catch (NoEffectiveMetadataChange $exception) {
            return back()
                ->withErrors([
                    'metadata' => $exception->getMessage(),
                ])
                ->withInput();
        }

        return redirect()
            ->route('archive.photos.show', $updated)
            ->with(
                'status',
                "Metadata revision {$updated->metadata_revision} recorded."
            );
    }

    private function assertEligible(MediaItem $mediaItem): void
    {
        abort_unless(
            $mediaItem->media_type === MediaType::Photo
            && $mediaItem->review_status === MediaReviewStatus::Approved
            && $mediaItem->approved_at !== null,
            404
        );
    }
}
