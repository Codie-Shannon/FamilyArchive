<?php

namespace Database\Seeders;

use App\Domain\Access\Models\AccountAccessEvent;
use App\Domain\Access\Models\ContributorSubmission;
use App\Domain\Access\Models\OriginalAccessGrant;
use App\Domain\Access\Models\UploadSession;
use App\Domain\Access\Models\UserInvitation;
use App\Domain\Intake\Enums\DuplicateStatus;
use App\Domain\Intake\Enums\IncomingProcessingStatus;
use App\Domain\Intake\Enums\IncomingReviewStatus;
use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Knowledge\Models\FamilyBranch;
use App\Domain\Media\Enums\DateConfidence;
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

final class ScreenshotGroup12DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('Screenshot Group 12 demo seeding is restricted to the local environment.');
        }

        DB::transaction(function (): void {
            $owner = $this->user('sg12-owner@example.test', 'Morgan Rimu', 'owner', 'approved');
            $harbour = FamilyBranch::query()->updateOrCreate(['branch_id' => 'SG12-BRN-HARBOUR'], [
                'name' => 'Harbour Branch',
                'description' => 'A fictional branch used only for SG12 access demonstrations.',
                'is_sensitive' => false,
                'review_state' => KnowledgeReviewState::Accepted,
                'confidence' => StructuredDateConfidence::High,
                'source_note' => 'Synthetic branch register.',
                'review_reason' => 'Approved fictional branch for local evidence.',
                'created_by' => $owner->id,
                'reviewed_by' => $owner->id,
                'reviewed_at' => now(),
                'metadata_revision' => 1,
            ]);
            $southern = FamilyBranch::query()->updateOrCreate(['branch_id' => 'SG12-BRN-SOUTHERN'], [
                'name' => 'Southern Branch',
                'description' => 'A second fictional branch proving scoped access.',
                'is_sensitive' => false,
                'review_state' => KnowledgeReviewState::Accepted,
                'confidence' => StructuredDateConfidence::High,
                'source_note' => 'Synthetic branch register.',
                'review_reason' => 'Approved fictional branch for local evidence.',
                'created_by' => $owner->id,
                'reviewed_by' => $owner->id,
                'reviewed_at' => now(),
                'metadata_revision' => 1,
            ]);

            $contributor = $this->user('sg12-contributor@example.test', 'Ari Kauri', 'contributor', 'approved', $harbour->id);
            $viewer = $this->user('sg12-viewer@example.test', 'Sam Tui', 'viewer', 'approved', $harbour->id);
            $pending = $this->user('sg12-pending@example.test', 'Casey Fern', 'viewer', 'pending', $southern->id);
            $admin = $this->user('sg12-admin@example.test', 'Taylor Kowhai', 'admin', 'approved');

            $familyItem = $this->media($owner, 'SG12-DEMO-FAMILY', 'Harbour Picnic Album', MediaVisibility::FamilyVisible);
            $this->media($owner, 'SG12-DEMO-HARBOUR', 'Harbour Branch Portraits', MediaVisibility::BranchVisible, $harbour->id);
            $this->media($owner, 'SG12-DEMO-SOUTHERN', 'Southern Branch Letters', MediaVisibility::BranchVisible, $southern->id);
            $this->media($owner, 'SG12-DEMO-PRIVATE', 'Custodian Review Record', MediaVisibility::PrivateArchive);

            UserInvitation::query()->firstOrCreate(['invitation_id' => '12000000-0000-4000-8000-000000000001'], [
                'email' => 'fictional.invitee@example.test',
                'name' => 'Jamie Ponga',
                'role' => 'viewer',
                'family_branch_id' => $harbour->id,
                'token_hash' => hash('sha256', 'sg12-fictional-invitation'),
                'invited_by' => $owner->id,
                'expires_at' => now()->addDays(7),
            ]);

            AccountAccessEvent::query()->firstOrCreate([
                'user_id' => $pending->id,
                'event_type' => 'invitation_accepted',
                'reason' => 'Fictional invitation accepted; owner review remains pending.',
            ], [
                'actor_id' => $owner->id,
                'new_values' => ['role' => 'viewer', 'account_state' => 'pending', 'family_branch_id' => $southern->id],
                'created_at' => now()->subHours(3),
            ]);
            AccountAccessEvent::query()->firstOrCreate([
                'user_id' => $contributor->id,
                'event_type' => 'account_access_updated',
                'reason' => 'Fictional identity and family connection confirmed.',
            ], [
                'actor_id' => $owner->id,
                'previous_values' => ['account_state' => 'pending'],
                'new_values' => ['role' => 'contributor', 'account_state' => 'approved', 'family_branch_id' => $harbour->id],
                'created_at' => now()->subHours(2),
            ]);

            OriginalAccessGrant::query()->firstOrCreate([
                'user_id' => $viewer->id,
                'media_item_id' => $familyItem->id,
                'reason' => 'Family history transcription for a fictional research request.',
            ], [
                'granted_by' => $owner->id,
                'effective_at' => now()->subDay(),
                'expires_at' => now()->addDays(14),
            ]);

            $session = UploadSession::query()->updateOrCreate(['session_id' => '12000000-0000-4000-8000-000000000002'], [
                'user_id' => $contributor->id,
                'title' => 'Fictional shoebox collection',
                'source_context' => 'Synthetic album and loose prints supplied for the SG12 evidence walkthrough.',
                'automation_preferences' => [
                    'automation_mode' => 'suggestions',
                    'auto_rotate' => true,
                    'deskew' => true,
                    'perspective' => true,
                    'crop_target' => 'photo_edge',
                    'exposure' => false,
                    'color' => false,
                    'denoise' => false,
                    'sharpen' => false,
                    'cleanup' => false,
                    'damage_repair' => false,
                    'upscale' => false,
                    'quality_warnings' => true,
                ],
                'expected_files' => 4,
                'received_files' => 3,
                'status' => 'paused',
                'expires_at' => now()->addDays(12),
            ]);

            foreach ([
                ['01', 'fictional-portrait-front.jpg', 'retained'],
                ['02', 'fictional-postcard-back.jpg', 'needs_info'],
                ['03', 'fictional-album-page.jpg', 'possible_duplicate'],
            ] as [$suffix, $filename, $status]) {
                $upload = IncomingUpload::query()->updateOrCreate(['upload_id' => 'UP-SG12-DEMO-'.$suffix], [
                    'uploader_id' => $contributor->id,
                    'original_filename' => $filename,
                    'incoming_path' => 'sg12-demo/quarantine/'.$filename,
                    'mime_type' => 'image/jpeg',
                    'extension' => 'jpg',
                    'file_size_bytes' => 1800000,
                    'width' => 2400,
                    'height' => 1600,
                    'sha256' => hash('sha256', 'sg12-'.$suffix),
                    'processing_status' => IncomingProcessingStatus::Pending,
                    'review_status' => IncomingReviewStatus::PendingReview,
                    'duplicate_status' => DuplicateStatus::NotChecked,
                    'source_file_retained' => true,
                    'retained_at' => now()->subHour(),
                    'submitted_at' => now()->subHour(),
                ]);
                ContributorSubmission::query()->updateOrCreate(['submission_id' => 'SUB-SG12-DEMO-'.$suffix], [
                    'user_id' => $contributor->id,
                    'upload_session_id' => $session->id,
                    'incoming_upload_id' => $upload->id,
                    'status' => $status,
                    'original_name' => $filename,
                    'source_context' => $session->source_context,
                    'proposed_metadata' => ['session_title' => $session->title],
                    'automation_preferences' => $session->automation_preferences,
                    'reviewed_by' => $status === 'retained' ? null : $owner->id,
                    'reviewer_note' => $status === 'needs_info' ? 'Please add the postcard sender if known.' : ($status === 'possible_duplicate' ? 'Compare with the accepted album sequence.' : null),
                    'reviewed_at' => $status === 'retained' ? null : now()->subMinutes(20),
                ]);
            }

            $admin->forceFill(['family_connection' => 'Fictional archive co-custodian'])->save();
        });
    }

    private function user(string $email, string $name, string $role, string $state, ?int $branchId = null): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'password' => Hash::make('SG12Demo!2026'),
            'email_verified_at' => now(),
            'role' => $role,
            'account_state' => $state,
            'family_branch_id' => $branchId,
            'family_connection' => $branchId ? 'Fictional reviewed family connection' : null,
        ])->save();

        return $user;
    }

    private function media(User $owner, string $archiveId, string $title, MediaVisibility $visibility, ?int $branchId = null): MediaItem
    {
        $item = MediaItem::query()->updateOrCreate(['archive_id' => $archiveId], [
            'media_type' => MediaType::Photo,
            'title' => $title,
            'description' => 'Synthetic archive metadata used only for SG12 access evidence.',
            'story' => 'A fictional record demonstrating access scope without real family content.',
            'estimated_decade' => 1970,
            'date_confidence' => DateConfidence::DecadeOnly,
            'visibility' => $visibility,
            'review_status' => MediaReviewStatus::Approved,
            'sensitivity_status' => SensitivityStatus::NotFlagged,
            'family_branch_id' => $branchId,
            'created_by' => $owner->id,
            'approved_by' => $owner->id,
            'approved_at' => now(),
        ]);

        $this->derivatives($item, $archiveId);

        return $item;
    }

    private function derivatives(MediaItem $item, string $archiveId): void
    {
        $originalBytes = $this->imageBytes(1600, 1067, $archiveId, false);
        $thumbnailBytes = $this->imageBytes(960, 640, $archiveId, true);
        $originalPath = 'sg12-demo/'.$archiveId.'/original.webp';
        $thumbnailPath = 'sg12-demo/'.$archiveId.'/thumbnail.webp';
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
            'generation_recipe' => ['profile' => 'sg12-fictional-thumbnail', 'source' => 'generated'],
            'is_preferred' => true,
        ]);
    }

    /**
     * @param  positive-int  $width
     * @param  positive-int  $height
     */
    private function imageBytes(int $width, int $height, string $label, bool $thumbnail): string
    {
        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            throw new RuntimeException('Unable to allocate an SG12 fictional image.');
        }
        $seed = abs(crc32($label));
        $sky = $this->color($image, 25 + ($seed % 45), 75 + ($seed % 55), 95 + ($seed % 65));
        $ground = $this->color($image, 65 + ($seed % 55), 75 + ($seed % 45), 45 + ($seed % 40));
        $cream = $this->color($image, 240, 232, 204);
        imagefilledrectangle($image, 0, 0, $width, (int) ($height * .62), $sky);
        imagefilledrectangle($image, 0, (int) ($height * .62), $width, $height, $ground);
        imagefilledellipse($image, (int) ($width * .22), (int) ($height * .28), (int) ($width * .28), (int) ($height * .38), $cream);
        imagefilledellipse($image, (int) ($width * .68), (int) ($height * .35), (int) ($width * .32), (int) ($height * .42), $cream);
        imagestring($image, 5, (int) ($width * .05), (int) ($height * .88), $thumbnail ? 'FICTIONAL FAMILY DERIVATIVE' : 'FICTIONAL ARCHIVE ORIGINAL', $cream);
        ob_start();
        $encoded = imagewebp($image, null, 82);
        $bytes = ob_get_clean();
        imagedestroy($image);
        if (! $encoded || $bytes === '') {
            throw new RuntimeException('Unable to encode an SG12 fictional image.');
        }

        return $bytes;
    }

    /**
     * @param  int<0, 255>  $red
     * @param  int<0, 255>  $green
     * @param  int<0, 255>  $blue
     */
    private function color(\GdImage $image, int $red, int $green, int $blue): int
    {
        $color = imagecolorallocate($image, $red, $green, $blue);
        if ($color === false) {
            throw new RuntimeException('Unable to allocate an SG12 fictional image colour.');
        }

        return $color;
    }
}
