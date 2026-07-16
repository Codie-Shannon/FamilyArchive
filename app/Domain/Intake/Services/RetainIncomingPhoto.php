<?php

namespace App\Domain\Intake\Services;

use App\Domain\Intake\Contracts\NoOverwriteQuarantineWriter;
use App\Domain\Intake\Exceptions\QuarantinePersistenceException;
use App\Domain\Intake\Models\IncomingUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

final class RetainIncomingPhoto
{
    public function __construct(
        private PhotoIntakeValidator $validator,
        private NoOverwriteQuarantineWriter $writer,
    ) {}

    public function retain(IncomingUpload $upload, UploadedFile $file): IncomingUpload
    {
        $facts = $this->validator->validate($file);
        if ($facts->sizeBytes !== $upload->file_size_bytes || $facts->mimeType !== $upload->mime_type || $facts->extension !== $upload->extension) {
            throw new QuarantinePersistenceException('The validated source facts no longer match the IncomingUpload record.');
        }

        $path = $file->getRealPath();
        $source = is_string($path) ? @fopen($path, 'rb') : false;
        if ($source === false) {
            throw new QuarantinePersistenceException('The validated upload stream is unavailable.');
        }

        try {
            $object = $this->writer->write((string) $upload->incoming_path, $source);
        } finally {
            fclose($source);
        }

        if ($object->bytesWritten !== $facts->sizeBytes || $object->storedBytes !== $facts->sizeBytes || $object->bytesWritten !== $upload->file_size_bytes) {
            $this->writer->removeCreated($object);
            throw new QuarantinePersistenceException('Quarantine byte verification failed.');
        }

        try {
            return DB::transaction(function () use ($upload, $object): IncomingUpload {
                $locked = IncomingUpload::query()->lockForUpdate()->findOrFail($upload->id);
                if ($locked->source_file_retained) {
                    throw new QuarantinePersistenceException('This IncomingUpload is already retained.');
                }
                $locked->forceFill([
                    'sha256' => strtolower($object->sha256),
                    'source_file_retained' => true,
                    'retained_at' => now(),
                ])->save();

                return $locked->refresh();
            });
        } catch (Throwable $e) {
            $this->writer->removeCreated($object);
            throw $e;
        }
    }
}
