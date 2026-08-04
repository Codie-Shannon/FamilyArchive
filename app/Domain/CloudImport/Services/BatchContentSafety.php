<?php

namespace App\Domain\CloudImport\Services;

use App\Domain\CloudImport\ValueObjects\BatchSafetyPolicy;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BatchContentSafety
{
    public const CLEAR = 'clear';

    public const IDENTIFICATION_DOCUMENT = 'identification_document';

    public const HISTORICAL_IDENTIFICATION_DOCUMENT = 'historical_identification_document';

    public const SENSITIVE_MINOR_IMAGE = 'sensitive_minor_image';

    public const SUSPECTED_ILLEGAL_CONTENT = 'suspected_illegal_content';

    /** @return list<string> */
    public static function classifications(): array
    {
        return [
            self::CLEAR,
            self::IDENTIFICATION_DOCUMENT,
            self::HISTORICAL_IDENTIFICATION_DOCUMENT,
            self::SENSITIVE_MINOR_IMAGE,
            self::SUSPECTED_ILLEGAL_CONTENT,
        ];
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::CLEAR => 'No safety restriction',
            self::IDENTIFICATION_DOCUMENT => 'Identification document',
            self::HISTORICAL_IDENTIFICATION_DOCUMENT => 'Historical identification document',
            self::SENSITIVE_MINOR_IMAGE => 'Sensitive image involving a minor',
            self::SUSPECTED_ILLEGAL_CONTENT => 'Suspected illegal or exploitative content',
        ];
    }

    public function policy(object $session): BatchSafetyPolicy
    {
        return BatchSafetyPolicy::fromManifest($this->manifest($session));
    }

    public function updatePolicy(object $session, User $actor, bool $blockIdentification, bool $blockSensitiveMinors): BatchSafetyPolicy
    {
        abort_unless($actor->role === 'owner', 403);

        $policy = new BatchSafetyPolicy($blockIdentification, $blockSensitiveMinors);
        $manifest = $this->manifest($session);
        $manifest['content_safety'] = $policy->toArray();

        DB::table('cloud_import_sessions')->where('id', data_get($session, 'id'))->update([
            'source_manifest' => json_encode($manifest, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);

        return $policy;
    }

    /**
     * @param  array<int|string, array{classification?: string|null, document_year?: int|string|null}>  $payload
     * @param  list<int>  $selectedIds
     */
    public function classifySelected(object $session, User $actor, array $payload, array $selectedIds): void
    {
        abort_unless($actor->canManageTrustedIntake(), 403);

        $selected = array_fill_keys(array_map('intval', $selectedIds), true);
        $items = DB::table('cloud_import_items')
            ->where('cloud_import_session_id', data_get($session, 'id'))
            ->whereIn('id', array_keys($selected))
            ->get();

        foreach ($items as $item) {
            $itemId = (int) data_get($item, 'id');
            $values = $payload[$itemId] ?? $payload[(string) $itemId] ?? [];

            $classification = (string) ($values['classification'] ?? self::CLEAR);
            if (! in_array($classification, self::classifications(), true)) {
                continue;
            }

            $year = filter_var($values['document_year'] ?? null, FILTER_VALIDATE_INT);
            $metadata = $this->metadata($item);
            $metadata['content_safety'] = [
                'classification' => $classification,
                'document_year' => $year === false ? null : $year,
                'classified_by' => $actor->id,
                'classified_at' => now()->toIso8601String(),
            ];

            DB::table('cloud_import_items')->where('id', $itemId)->update([
                'source_metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        }
    }

    /** @return array{classification:string, document_year:?int} */
    public function classification(object $item): array
    {
        $safety = $this->metadata($item)['content_safety'] ?? [];
        $safety = is_array($safety) ? $safety : [];
        $classification = (string) ($safety['classification'] ?? self::CLEAR);
        if (! in_array($classification, self::classifications(), true)) {
            $classification = self::CLEAR;
        }
        $year = filter_var($safety['document_year'] ?? null, FILTER_VALIDATE_INT);

        return [
            'classification' => $classification,
            'document_year' => $year === false ? null : $year,
        ];
    }

    public function assertDecisionAllowed(object $session, object $item, User $actor, string $decision): void
    {
        if (in_array($decision, ['hold', 'reject'], true)) {
            return;
        }

        $classification = $this->classification($item);
        $type = $classification['classification'];
        $policy = $this->policy($session);

        if ($type === self::SUSPECTED_ILLEGAL_CONTENT) {
            throw ValidationException::withMessages([
                'items' => 'Suspected illegal or exploitative content is permanently blocked and cannot be approved or preserved.',
            ]);
        }

        if ($type === self::HISTORICAL_IDENTIFICATION_DOCUMENT) {
            if (! $policy->yearIsDefinitelyHistorical($classification['document_year'])) {
                throw ValidationException::withMessages([
                    'items' => 'Historical identification requires a year of 1964 or earlier. Documents dated 1965 or with an uncertain year remain blocked for review.',
                ]);
            }
            if ($decision !== 'preserve_private' || $actor->role !== 'owner') {
                throw ValidationException::withMessages([
                    'items' => 'Historical identification may only be retained privately by the owner.',
                ]);
            }

            return;
        }

        if ($type === self::IDENTIFICATION_DOCUMENT && $policy->blockIdentificationDocuments) {
            throw ValidationException::withMessages([
                'items' => 'Identification-document blocking is enabled for this batch. The owner must change the batch policy before approval.',
            ]);
        }

        if ($type === self::SENSITIVE_MINOR_IMAGE && $policy->blockSensitiveMinorImages) {
            throw ValidationException::withMessages([
                'items' => 'Sensitive-minor-image blocking is enabled for this batch. The owner must change the batch policy before approval.',
            ]);
        }

        if ($decision === 'preserve_private') {
            if ($actor->role !== 'owner') {
                abort(403);
            }
            if ($type === self::CLEAR) {
                throw ValidationException::withMessages(['items' => 'Private preservation is reserved for safety-classified material.']);
            }

            return;
        }
    }

    /** @return array<string, mixed> */
    private function manifest(object $session): array
    {
        $manifest = json_decode((string) data_get($session, 'source_manifest', '{}'), true);

        return is_array($manifest) ? $manifest : [];
    }

    /** @return array<string, mixed> */
    private function metadata(object $item): array
    {
        $metadata = json_decode((string) data_get($item, 'source_metadata', '{}'), true);

        return is_array($metadata) ? $metadata : [];
    }
}
