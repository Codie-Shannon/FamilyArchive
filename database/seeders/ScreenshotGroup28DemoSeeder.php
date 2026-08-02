<?php

namespace Database\Seeders;

use App\Domain\Knowledge\Enums\EventType;
use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Knowledge\Enums\LocationPrecision;
use App\Domain\Knowledge\Enums\PersonDatePrecision;
use App\Domain\Knowledge\Enums\PersonNameCertainty;
use App\Domain\Knowledge\Models\ArchiveEvent;
use App\Domain\Knowledge\Models\ArchiveLocation;
use App\Domain\Knowledge\Models\ArchivePerson;
use App\Domain\Knowledge\Models\CuratedCollection;
use App\Domain\Knowledge\Models\FamilyBranch;
use App\Domain\Media\Enums\DateConfidence;
use App\Domain\Media\Enums\DatePrecision;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Enums\SensitivityStatus;
use App\Domain\Media\Enums\StructuredDateConfidence;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class ScreenshotGroup28DemoSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('local'), 403, 'The SG28 dataset is local-only.');

        $owner = $this->user('sg28-owner@example.test', 'Jordan Vale', 'owner');
        $this->user('sg28-member@example.test', 'Ria Harbour', 'viewer');
        $this->user('sg28-trusted@example.test', 'Ari Kauri', 'trusted_contributor');

        $branch = FamilyBranch::query()->updateOrCreate(['branch_id' => 'SG28-BRN-HARBOUR'], [
            'name' => 'Harbour Family',
            'description' => 'A fictional family line gathered into one browsable album.',
            'is_sensitive' => false,
            'review_state' => KnowledgeReviewState::Accepted,
            'confidence' => StructuredDateConfidence::High,
            'source_note' => 'Synthetic reviewed family register.',
            'review_reason' => 'Accepted for local album evidence.',
            'created_by' => $owner->id,
            'reviewed_by' => $owner->id,
            'reviewed_at' => now(),
            'metadata_revision' => 1,
        ]);

        $place = ArchiveLocation::query()->updateOrCreate(['location_id' => 'SG28-LOC-GLOSSOP'], [
            'label' => 'Glossop Family Home',
            'subtitle' => 'The red-roofed house by the harbour',
            'address' => '14 Fictional Lane, Napier 4110',
            'country_code' => 'NZ',
            'region' => "Hawke's Bay",
            'locality' => 'Napier',
            'precision' => LocationPrecision::Exact,
            'is_sensitive' => false,
            'review_state' => KnowledgeReviewState::Accepted,
            'confidence' => StructuredDateConfidence::High,
            'source_note' => 'Synthetic reviewed address prepared for SG28 evidence.',
            'review_reason' => 'Accepted for local album evidence.',
            'created_by' => $owner->id,
            'reviewed_by' => $owner->id,
            'reviewed_at' => now(),
            'metadata_revision' => 1,
        ]);

        $event = ArchiveEvent::query()->updateOrCreate(['event_id' => 'SG28-EVT-REUNION'], [
            'name' => 'Harbour Family Reunion',
            'type' => EventType::Reunion,
            'starts_on' => null,
            'ends_on' => null,
            'date_precision' => DatePrecision::YearOnly,
            'date_year' => 1988,
            'estimated_decade' => null,
            'date_confidence' => StructuredDateConfidence::High,
            'date_source_note' => 'Synthetic album caption.',
            'archive_location_id' => $place->id,
            'description' => 'A fictional summer reunion whose reviewed photos also form place and event albums.',
            'review_state' => KnowledgeReviewState::Accepted,
            'review_reason' => 'Accepted for local album evidence.',
            'created_by' => $owner->id,
            'reviewed_by' => $owner->id,
            'reviewed_at' => now(),
            'metadata_revision' => 1,
        ]);

        $person = ArchivePerson::query()->updateOrCreate(['person_id' => 'SG28-PER-MARA'], [
            'display_name' => 'Mara Harbour',
            'alternate_names' => ['Mara Vale'],
            'name_certainty' => PersonNameCertainty::Confirmed,
            'birth_on' => null,
            'birth_year' => 1924,
            'birth_decade' => null,
            'birth_precision' => PersonDatePrecision::YearOnly,
            'death_on' => null,
            'death_year' => 2008,
            'death_decade' => null,
            'death_precision' => PersonDatePrecision::YearOnly,
            'life_state' => 'deceased',
            'identity_state' => 'confirmed',
            'fact_confidence' => StructuredDateConfidence::High,
            'source_note' => 'Synthetic reviewed family register.',
            'is_private' => false,
            'family_branch_id' => $branch->id,
            'notes' => 'A fictional identity used only for album evidence.',
            'review_state' => KnowledgeReviewState::Accepted,
            'review_reason' => 'Accepted for local album evidence.',
            'created_by' => $owner->id,
            'reviewed_by' => $owner->id,
            'reviewed_at' => now(),
            'metadata_revision' => 1,
        ]);

        $photos = collect([
            ['SG28-PH-001', 'Arrival at Glossop House', 1952],
            ['SG28-PH-002', 'Garden Tea on the Verandah', 1958],
            ['SG28-PH-003', 'Mara and the Harbour Cousins', 1964],
            ['SG28-PH-004', 'Summer Reunion Portrait', 1988],
            ['SG28-PH-005', 'Anniversary Table', 1991],
            ['SG28-PH-006', 'The Red-Roofed House', 1976],
            ['SG28-PH-007', 'Harbour Picnic Afternoon', 1982],
            ['SG28-PH-008', 'Family Album Notes', 1998],
        ])->map(fn (array $photo, int $index): MediaItem => $this->photo($owner, $photo[0], $photo[1], $photo[2], $index));

        foreach ($photos->take(4) as $photo) {
            DB::table('archive_event_media')->updateOrInsert([
                'archive_event_id' => $event->id,
                'media_item_id' => $photo->id,
            ], [
                'confidence' => 'confirmed',
                'source_note' => 'Synthetic reviewed album link.',
                'reviewed_by' => $owner->id,
                'reviewed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($photos->only([0, 2, 4, 6]) as $photo) {
            DB::table('archive_person_media')->updateOrInsert([
                'archive_person_id' => $person->id,
                'media_item_id' => $photo->id,
            ], [
                'context' => 'Mara appears in this fictional reviewed photograph.',
                'confidence' => 'confirmed',
                'reviewed_by' => $owner->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->album($owner, 'ALB-SG28-HARBOUR', 'Harbour Memories', 'A trusted-family selection spanning the home, reunions and summer afternoons.', $photos->only([0, 1, 2, 5, 6])->values()->all());
        $this->album($owner, 'ALB-SG28-ANNIVERSARY', 'Anniversary Album', 'A small shared album curated without duplicating the archive originals.', $photos->only([3, 4, 7])->values()->all());
    }

    private function user(string $email, string $name, string $role): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'password' => Hash::make('SG28Demo!2026'),
            'email_verified_at' => now(),
            'role' => $role,
            'account_state' => 'approved',
            'family_connection' => 'Fictional SG28 family member',
        ])->save();

        return $user;
    }

    private function photo(User $owner, string $archiveId, string $title, int $year, int $index): MediaItem
    {
        $item = MediaItem::query()->updateOrCreate(['archive_id' => $archiveId], [
            'media_type' => MediaType::Photo,
            'title' => $title,
            'description' => 'A synthetic family photograph prepared only for album-centred archive evidence.',
            'story' => 'Fictional archive content with no connection to a real person or family.',
            'canonical_date' => null,
            'date_precision' => DatePrecision::YearOnly,
            'date_year' => $year,
            'estimated_decade' => null,
            'date_confidence' => DateConfidence::Exact,
            'visibility' => MediaVisibility::FamilyVisible,
            'review_status' => MediaReviewStatus::Approved,
            'sensitivity_status' => SensitivityStatus::NotFlagged,
            'family_branch_id' => null,
            'created_by' => $owner->id,
            'approved_by' => $owner->id,
            'approved_at' => now()->subMinutes(20 - $index),
        ]);

        $this->derivatives($item, $archiveId, $title, $index);

        return $item;
    }

    /** @param array<int, MediaItem> $photos */
    private function album(User $owner, string $collectionId, string $name, string $description, array $photos): void
    {
        $album = CuratedCollection::query()->updateOrCreate(['collection_id' => $collectionId], [
            'name' => $name,
            'description' => $description,
            'is_published' => true,
            'curated_by' => $owner->id,
        ]);
        $membership = [];
        foreach ($photos as $position => $photo) {
            $membership[$photo->id] = ['added_by' => $owner->id, 'position' => $position + 1];
        }
        $album->mediaItems()->sync($membership);
    }

    private function derivatives(MediaItem $item, string $archiveId, string $title, int $index): void
    {
        $originalBytes = $this->imageBytes(1600, 1067, $title, $index, false);
        $thumbnailBytes = $this->imageBytes(960, 640, $title, $index, true);
        $originalPath = 'sg28-demo/'.$archiveId.'/original.webp';
        $thumbnailPath = 'sg28-demo/'.$archiveId.'/thumbnail.webp';
        Storage::disk('archive_originals')->put($originalPath, $originalBytes);
        Storage::disk('archive_derivatives')->put($thumbnailPath, $thumbnailBytes);

        $original = MediaFileVersion::query()->updateOrCreate(['storage_path' => $originalPath], [
            'media_item_id' => $item->id,
            'parent_version_id' => null,
            'version_type' => MediaFileVersionType::Original,
            'storage_disk' => 'archive_originals',
            'mime_type' => 'image/webp',
            'extension' => 'webp',
            'file_size_bytes' => strlen($originalBytes),
            'width' => 1600,
            'height' => 1067,
            'sha256' => hash('sha256', $originalBytes),
            'generation_status' => GenerationStatus::Ready,
            'is_preferred' => true,
        ]);
        MediaFileVersion::query()->updateOrCreate(['storage_path' => $thumbnailPath], [
            'media_item_id' => $item->id,
            'parent_version_id' => $original->id,
            'version_type' => MediaFileVersionType::Thumbnail,
            'storage_disk' => 'archive_derivatives',
            'mime_type' => 'image/webp',
            'extension' => 'webp',
            'file_size_bytes' => strlen($thumbnailBytes),
            'width' => 960,
            'height' => 640,
            'sha256' => hash('sha256', $thumbnailBytes),
            'generation_status' => GenerationStatus::Ready,
            'generation_recipe' => ['profile' => 'sg28-fictional-thumbnail', 'source' => 'generated'],
            'is_preferred' => true,
        ]);
    }

    /**
     * @param  positive-int  $width
     * @param  positive-int  $height
     */
    private function imageBytes(int $width, int $height, string $label, int $index, bool $thumbnail): string
    {
        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            throw new RuntimeException('Unable to allocate an SG28 fictional image.');
        }

        $palettes = [
            [[46, 82, 93], [197, 160, 92]], [[91, 63, 65], [193, 144, 112]],
            [[50, 77, 59], [188, 166, 113]], [[64, 65, 97], [189, 137, 121]],
            [[92, 72, 42], [183, 154, 101]], [[42, 78, 88], [170, 132, 91]],
            [[72, 54, 76], [177, 145, 104]], [[54, 72, 85], [181, 151, 119]],
        ];
        [$top, $bottom] = $palettes[$index % count($palettes)];
        $sky = $this->color($image, ...$top);
        $ground = $this->color($image, ...$bottom);
        $cream = $this->color($image, 242, 232, 208);
        $shadow = $this->color($image, 32, 29, 27);
        imagefilledrectangle($image, 0, 0, $width, (int) ($height * .62), $sky);
        imagefilledrectangle($image, 0, (int) ($height * .62), $width, $height, $ground);
        imagefilledrectangle($image, (int) ($width * .08), (int) ($height * .18), (int) ($width * .92), (int) ($height * .76), $cream);
        imagefilledrectangle($image, (int) ($width * .12), (int) ($height * .23), (int) ($width * .88), (int) ($height * .71), $shadow);
        imagefilledellipse($image, (int) ($width * .38), (int) ($height * .43), (int) ($width * .20), (int) ($height * .31), $cream);
        imagefilledellipse($image, (int) ($width * .63), (int) ($height * .43), (int) ($width * .20), (int) ($height * .31), $cream);
        imagestring($image, 5, (int) ($width * .12), (int) ($height * .84), strtoupper($label), $cream);
        imagestring($image, 3, (int) ($width * .12), (int) ($height * .90), $thumbnail ? 'FICTIONAL FAMILY THUMBNAIL' : 'FICTIONAL ARCHIVE ORIGINAL', $cream);

        ob_start();
        $encoded = imagewebp($image, null, 84);
        $bytes = ob_get_clean();
        imagedestroy($image);
        if (! $encoded || $bytes === '') {
            throw new RuntimeException('Unable to encode an SG28 fictional image.');
        }

        return $bytes;
    }

    /** @param int<0, 255> ...$channels */
    private function color(\GdImage $image, int ...$channels): int
    {
        $color = imagecolorallocate($image, $channels[0], $channels[1], $channels[2]);
        if ($color === false) {
            throw new RuntimeException('Unable to allocate an SG28 fictional image colour.');
        }

        return $color;
    }
}
