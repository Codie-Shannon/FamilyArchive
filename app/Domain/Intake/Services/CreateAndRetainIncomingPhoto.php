<?php

namespace App\Domain\Intake\Services;

use App\Domain\Intake\Models\IncomingUpload;
use App\Models\User;
use Illuminate\Http\UploadedFile;

final class CreateAndRetainIncomingPhoto
{
    public function __construct(
        private CreateIncomingPhotoRecord $records,
        private RetainIncomingPhoto $retention,
    ) {}

    public function create(User $owner, UploadedFile $file): IncomingUpload
    {
        $upload = $this->records->create($owner, $file);

        return $this->retention->retain($upload, $file);
    }
}
