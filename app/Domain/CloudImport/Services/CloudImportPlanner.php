<?php

namespace App\Domain\CloudImport\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CloudImportPlanner
{
    /** @param list<array{external_id: string, media_type: string, original_name: string}> $items */
    public function plan(User $user, string $provider, array $items): string
    {
        if (! in_array($provider, ['google_photos', 'apple_photos', 'manual_export'], true)) {
            throw ValidationException::withMessages(['provider' => 'Unsupported cloud import provider.']);
        }

        $supported = (array) config('cloud_imports.supported_media');
        foreach ($items as $item) {
            if (! in_array($item['media_type'], $supported, true)) {
                throw ValidationException::withMessages(['media_type' => 'Unsupported media type selected for import.']);
            }
        }

        return DB::transaction(function () use ($user, $provider, $items): string {
            $sessionId = (string) Str::uuid();
            $id = DB::table('cloud_import_sessions')->insertGetId([
                'session_id' => $sessionId,
                'user_id' => $user->id,
                'provider' => $provider,
                'state' => 'preflight',
                'selected_count' => count($items),
                'imported_count' => 0,
                'failed_count' => 0,
                'source_manifest' => json_encode(['provider' => $provider, 'selected' => count($items)], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($items as $item) {
                DB::table('cloud_import_items')->insert([
                    'cloud_import_session_id' => $id,
                    'external_id' => $item['external_id'],
                    'media_type' => $item['media_type'],
                    'original_name' => basename(str_replace('\\', '/', $item['original_name'])),
                    'state' => 'selected',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $sessionId;
        });
    }

    /** @return array{google_photos: bool, apple_photos: bool, apple_mode: string, document_ocr: bool} */
    public function readiness(): array
    {
        return [
            'google_photos' => filled(config('cloud_imports.providers.google_photos.client_id'))
                && filled(config('cloud_imports.providers.google_photos.client_secret')),
            'apple_photos' => config('cloud_imports.providers.apple_photos.native_connector_status') === 'validated',
            'apple_mode' => (string) config('cloud_imports.providers.apple_photos.mode'),
            'document_ocr' => (bool) config('cloud_imports.document_ocr'),
        ];
    }
}
