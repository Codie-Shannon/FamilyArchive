<?php

namespace App\Domain\CloudImport\ValueObjects;

final readonly class BatchSafetyPolicy
{
    public const HISTORICAL_DOCUMENT_AGE_YEARS = 61;

    public function __construct(
        public bool $blockIdentificationDocuments = true,
        public bool $blockSensitiveMinorImages = true,
    ) {}

    public static function defaults(): self
    {
        return new self;
    }

    /** @param array<string, mixed> $manifest */
    public static function fromManifest(array $manifest): self
    {
        $policy = $manifest['content_safety'] ?? [];
        $policy = is_array($policy) ? $policy : [];

        return new self(
            blockIdentificationDocuments: array_key_exists('identification_documents_blocked', $policy)
                ? (bool) $policy['identification_documents_blocked']
                : true,
            blockSensitiveMinorImages: array_key_exists('sensitive_minor_images_blocked', $policy)
                ? (bool) $policy['sensitive_minor_images_blocked']
                : true,
        );
    }

    /** @return array<string, bool|int> */
    public function toArray(): array
    {
        return [
            'identification_documents_blocked' => $this->blockIdentificationDocuments,
            'sensitive_minor_images_blocked' => $this->blockSensitiveMinorImages,
            'suspected_illegal_content_blocked' => true,
            'historical_document_age_years' => self::HISTORICAL_DOCUMENT_AGE_YEARS,
        ];
    }

    public function historicalCutoffYear(): int
    {
        return (int) now()->subYears(self::HISTORICAL_DOCUMENT_AGE_YEARS)->year;
    }

    public function yearIsDefinitelyHistorical(?int $year): bool
    {
        return $year !== null && $year < $this->historicalCutoffYear();
    }
}
