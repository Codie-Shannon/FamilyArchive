<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Derivatives\Actions\GeneratePhotoViewingDerivatives;
use App\Domain\Derivatives\Exceptions\DerivativeGenerationException;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

final class ViewingDerivativeController extends Controller
{
    public function index(GeneratePhotoViewingDerivatives $generator): View
    {
        $items = MediaItem::query()
            ->with('fileVersions')
            ->where('media_type', MediaType::Photo)
            ->where('review_status', MediaReviewStatus::Approved)
            ->latest('approved_at')
            ->limit(100)
            ->get();

        $eligible = $items->filter(fn (MediaItem $item): bool => $generator->isEligible($item));

        return view('admin.viewing-derivatives.index', compact('eligible'));
    }

    public function show(MediaItem $mediaItem, GeneratePhotoViewingDerivatives $generator): View
    {
        $mediaItem->load(['fileVersions.parentVersion']);
        $original = $mediaItem->fileVersions->first(fn (MediaFileVersion $version): bool => $version->version_type === MediaFileVersionType::Original
            && $version->generation_status === GenerationStatus::Ready
            && $version->is_preferred
        );

        $existing = $original instanceof MediaFileVersion
            ? $generator->matchingExisting($mediaItem, $original)
            : [];

        return view('admin.viewing-derivatives.show', [
            'mediaItem' => $mediaItem,
            'original' => $original,
            'webDisplay' => $existing[MediaFileVersionType::WebDisplay->value] ?? null,
            'thumbnail' => $existing[MediaFileVersionType::Thumbnail->value] ?? null,
            'eligible' => $generator->isEligible($mediaItem),
        ]);
    }

    public function store(
        Request $request,
        MediaItem $mediaItem,
        GeneratePhotoViewingDerivatives $generator,
    ): RedirectResponse {
        $result = $generator->handle($mediaItem, $request->user());
        $created = [];
        if ($result->createdWebDisplay) {
            $created[] = 'web display';
        }
        if ($result->createdThumbnail) {
            $created[] = 'thumbnail';
        }

        $message = $created === []
            ? 'Verified photo-v1 derivatives already existed; no files or rows were duplicated.'
            : 'Private '.implode(' and ', $created).' WebP derivatives were generated and integrity-verified.';

        return redirect()
            ->route('admin.viewing-derivatives.show', $mediaItem)
            ->with('status', $message);
    }

    public function preview(MediaFileVersion $version): Response
    {
        if (
            ! in_array($version->version_type, [MediaFileVersionType::WebDisplay, MediaFileVersionType::Thumbnail], true)
            || $version->generation_status !== GenerationStatus::Ready
            || $version->storage_disk !== 'archive_derivatives'
            || $version->mime_type !== 'image/webp'
        ) {
            abort(404);
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('archive_derivatives');
        if (! $disk->exists($version->storage_path)) {
            abort(404);
        }

        $bytes = $disk->get($version->storage_path);
        if (
            strlen($bytes) !== $version->file_size_bytes
            || ! hash_equals(strtolower($version->sha256), hash('sha256', $bytes))
        ) {
            throw new DerivativeGenerationException('The private derivative preview failed integrity verification.');
        }

        return response($bytes, 200, [
            'Content-Type' => 'image/webp',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
