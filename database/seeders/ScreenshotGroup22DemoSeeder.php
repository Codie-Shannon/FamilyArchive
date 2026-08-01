<?php

namespace Database\Seeders;

use App\Domain\Access\Models\ContributorSubmission;
use App\Domain\Access\Models\UploadSession;
use App\Domain\CloudImport\Services\BrowserUploadBatch;
use App\Domain\CloudImport\Services\TrustedBatchReview;
use App\Domain\Intake\Services\CreateAndRetainIncomingPhoto;
use App\Models\User;
use GdImage;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class ScreenshotGroup22DemoSeeder extends Seeder
{
    private const SESSION_ID = '22000000-0000-4000-8000-000000000001';

    public function run(): void
    {
        abort_unless(app()->environment('local'), 403, 'The SG22 dataset is local-only.');

        $reviewer = $this->user('sg22-reviewer@example.test', 'Mere Raukura', 'trusted_contributor');
        $this->user('sg22-owner@example.test', 'Jordan Vale', 'owner');

        if (UploadSession::query()->where('session_id', self::SESSION_ID)->exists()) {
            DB::table('cloud_import_sessions')->where('session_id', self::SESSION_ID)->update([
                'user_id' => $reviewer->id,
                'updated_at' => now(),
            ]);

            return;
        }

        $session = DB::transaction(function () use ($reviewer): UploadSession {
            $session = UploadSession::query()->create([
                'session_id' => self::SESSION_ID,
                'user_id' => $reviewer->id,
                'title' => 'Fictional family album intake',
                'source_context' => 'Generated evidence photos from a fictional labelled album.',
                'automation_preferences' => [
                    'automation_mode' => 'candidates',
                    'crop_target' => 'photo_edge',
                    'auto_rotate' => true,
                    'deskew' => true,
                    'perspective' => true,
                    'exposure' => true,
                    'color' => true,
                    'denoise' => true,
                    'sharpen' => true,
                    'cleanup' => false,
                    'damage_repair' => false,
                    'upscale' => false,
                    'quality_warnings' => true,
                ],
                'expected_files' => 5,
                'received_files' => 0,
                'status' => 'open',
                'expires_at' => now()->addDays(14),
            ]);
            app(BrowserUploadBatch::class)->open($reviewer, $session);

            return $session;
        });

        $directory = storage_path('framework/testing/sg22-fictional-browser-batch');
        File::deleteDirectory($directory);
        File::ensureDirectoryExists($directory);

        try {
            foreach (range(1, 5) as $theme) {
                $name = 'fictional-album-photo-'.str_pad((string) $theme, 2, '0', STR_PAD_LEFT).'.jpg';
                $path = $directory.'/'.$name;
                File::put($path, $this->imageBytes($theme));
                $incoming = app(CreateAndRetainIncomingPhoto::class)->create(
                    $reviewer,
                    new UploadedFile($path, $name, 'image/jpeg', UPLOAD_ERR_OK, true),
                );
                $submission = ContributorSubmission::query()->create([
                    'submission_id' => 'SG22-SUB-'.str_pad((string) $theme, 3, '0', STR_PAD_LEFT),
                    'user_id' => $reviewer->id,
                    'upload_session_id' => $session->id,
                    'incoming_upload_id' => $incoming->id,
                    'status' => 'retained',
                    'original_name' => $incoming->original_filename,
                    'source_context' => $session->source_context,
                    'proposed_metadata' => ['session_title' => $session->title],
                    'automation_preferences' => $session->automation_preferences,
                ]);
                app(BrowserUploadBatch::class)->retain($session->fresh(), $submission, $incoming);
            }
            app(BrowserUploadBatch::class)->checkpoint($session->fresh());
        } finally {
            File::deleteDirectory($directory);
        }

        app(TrustedBatchReview::class)->prepare(self::SESSION_ID, $reviewer, 25);
        $sessionKey = DB::table('cloud_import_sessions')->where('session_id', self::SESSION_ID)->value('id');
        $attentionItem = DB::table('cloud_import_items')
            ->where('cloud_import_session_id', $sessionKey)
            ->where('position', 4)
            ->first();
        if ($attentionItem === null) {
            throw new RuntimeException('The SG22 delegated review batch was not prepared correctly.');
        }
        DB::table('cloud_import_items')->where('id', $attentionItem->id)->update([
            'attention_code' => 'crop_check',
            'updated_at' => now(),
        ]);
        DB::table('cloud_import_sessions')->where('id', $sessionKey)->update([
            'attention_count' => 1,
            'review_state' => 'needs_attention',
            'updated_at' => now(),
        ]);
    }

    private function user(string $email, string $name, string $role): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'password' => Hash::make('SG22Demo!2026'),
            'email_verified_at' => now(),
            'role' => $role,
            'account_state' => 'approved',
            'family_connection' => 'Fictional SG22 evidence identity',
        ])->save();

        return $user;
    }

    private function imageBytes(int $theme): string
    {
        $image = imagecreatetruecolor(1400, 1000);
        if (! $image instanceof GdImage) {
            throw new RuntimeException('Unable to allocate an SG22 fictional image.');
        }

        $background = $this->color($image, 226, 218, 200);
        $frame = $this->color($image, 47 + ($theme * 5), 51 + ($theme * 4), 56 + ($theme * 3));
        $mat = $this->color($image, 246, 237, 216);
        $photo = $this->color($image, 83 + ($theme * 7), 108 + ($theme * 7), 118 + ($theme * 6));
        $light = $this->color($image, 249, 242, 224);
        $dark = $this->color($image, 30, 35, 40);
        $accent = $this->color($image, 130 + ($theme * 6), 67 + ($theme * 4), 75 + ($theme * 3));

        imagefilledrectangle($image, 0, 0, 1400, 1000, $background);
        imagefilledrectangle($image, 80, 65, 1320, 935, $frame);
        imagefilledrectangle($image, 125, 110, 1275, 890, $mat);
        imagefilledrectangle($image, 195, 170, 1205, 820, $photo);
        imagefilledellipse($image, 500, 415, 255, 305, $light);
        imagefilledellipse($image, 890, 405, 255, 305, $light);
        imagefilledellipse($image, 500, 415, 95, 125, $dark);
        imagefilledellipse($image, 890, 405, 95, 125, $dark);
        imagefilledrectangle($image, 370, 560, 635, 820, $accent);
        imagefilledrectangle($image, 755, 550, 1020, 820, $dark);
        imagestring($image, 5, 225, 190, 'FICTIONAL SG22 ALBUM PHOTO '.str_pad((string) $theme, 2, '0', STR_PAD_LEFT), $light);
        imagestring($image, 5, 430, 850, 'SYNTHETIC EVIDENCE ONLY', $dark);

        ob_start();
        $encoded = imagejpeg($image, null, 91);
        $bytes = ob_get_clean();
        unset($image);

        if (! $encoded || $bytes === '') {
            throw new RuntimeException('Unable to encode an SG22 fictional image.');
        }

        return $bytes;
    }

    private function color(GdImage $image, int $red, int $green, int $blue): int
    {
        $color = imagecolorallocate($image, min(255, $red), min(255, $green), min(255, $blue));
        if ($color === false) {
            throw new RuntimeException('Unable to allocate an SG22 fictional image colour.');
        }

        return $color;
    }
}
