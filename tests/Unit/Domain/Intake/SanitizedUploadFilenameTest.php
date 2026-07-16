<?php

use App\Domain\Intake\ValueObjects\SanitizedUploadFilename;

it('creates a deterministic safe lowercase filename', function () {
    expect((string) new SanitizedUploadFilename('Fictional Grid Photo.JPG', 'jpg'))->toBe('fictional-grid-photo.jpg');
});
it('rejects path-bearing filenames', function () {
    new SanitizedUploadFilename('../private.jpg', 'jpg');
})->throws(InvalidArgumentException::class);
