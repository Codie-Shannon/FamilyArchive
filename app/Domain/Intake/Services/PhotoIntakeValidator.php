<?php

namespace App\Domain\Intake\Services;

use App\Domain\Intake\ValueObjects\SanitizedUploadFilename;
use App\Domain\Intake\ValueObjects\ValidatedPhotoFacts;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class PhotoIntakeValidator
{
    public function validate(UploadedFile $file): ValidatedPhotoFacts
    {
        $size = (int) $file->getSize();
        $max = (int) config('archive.photo_intake.max_bytes');
        if ($size < 1 || $size > $max) {
            $this->fail('photo', 'The photo size is outside the configured Group 04 limit.');
        }
        $path = $file->getRealPath();
        if (! is_string($path) || $path === '') {
            $this->fail('photo', 'The temporary upload is unreadable.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (! is_string($mime)) {
            $this->fail('photo', 'The photo MIME type could not be detected.');
        }
        $allowed = config('archive.photo_intake.mime_extensions', []);
        if (! isset($allowed[$mime])) {
            $this->fail('photo', 'Only JPEG, PNG, WebP and TIFF photos are supported.');
        }
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, $allowed[$mime], true)) {
            $this->fail('photo', 'The detected MIME type and filename extension do not match.');
        }
        try {
            new SanitizedUploadFilename($file->getClientOriginalName(), $ext);
        } catch (\InvalidArgumentException $e) {
            $this->fail('photo', $e->getMessage());
        }
        $image = @getimagesize($path);
        if ($image === false || $image['mime'] !== $mime) {
            $this->fail('photo', 'A readable image header matching the detected MIME type is required.');
        }
        $w = (int) $image[0];
        $h = (int) $image[1];
        if ($w < 1 || $h < 1 || $w > (int) config('archive.photo_intake.max_width') || $h > (int) config('archive.photo_intake.max_height') || $w * $h > (int) config('archive.photo_intake.max_pixels')) {
            $this->fail('photo', 'The image dimensions exceed the configured Group 04 limit.');
        }

        return new ValidatedPhotoFacts($file->getClientOriginalName(), $mime, $ext, $size, $w, $h);
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
