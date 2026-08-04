<?php

use App\Domain\CloudImport\Services\BatchContentSafety;
use App\Domain\CloudImport\ValueObjects\BatchSafetyPolicy;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

it('uses safe defaults and applies the 61 year historical identity threshold conservatively', function () {
    Carbon::setTestNow('2026-08-04 12:00:00');

    try {
        $policy = BatchSafetyPolicy::fromManifest([]);

        expect($policy->blockIdentificationDocuments)->toBeTrue()
            ->and($policy->blockSensitiveMinorImages)->toBeTrue()
            ->and($policy->historicalCutoffYear())->toBe(1965)
            ->and($policy->yearIsDefinitelyHistorical(1964))->toBeTrue()
            ->and($policy->yearIsDefinitelyHistorical(1965))->toBeFalse()
            ->and($policy->yearIsDefinitelyHistorical(null))->toBeFalse();
    } finally {
        Carbon::setTestNow();
    }
});

it('never permits suspected illegal child imagery to be approved or privately preserved', function () {
    $service = app(BatchContentSafety::class);
    $owner = new User(['role' => 'owner']);
    $session = (object) [
        'source_manifest' => json_encode([
            'content_safety' => [
                'identification_documents_blocked' => false,
                'sensitive_minor_images_blocked' => false,
            ],
        ], JSON_THROW_ON_ERROR),
    ];
    $item = (object) [
        'source_metadata' => json_encode([
            'content_safety' => [
                'classification' => BatchContentSafety::SUSPECTED_ILLEGAL_CONTENT,
            ],
        ], JSON_THROW_ON_ERROR),
    ];

    expect(fn () => $service->assertDecisionAllowed($session, $item, $owner, 'approve_original'))
        ->toThrow(ValidationException::class, 'permanently blocked')
        ->and(fn () => $service->assertDecisionAllowed($session, $item, $owner, 'preserve_private'))
        ->toThrow(ValidationException::class, 'permanently blocked');
});

it('requires a definitely historical identity document and an owner for private preservation', function () {
    Carbon::setTestNow('2026-08-04 12:00:00');

    try {
        $service = app(BatchContentSafety::class);
        $owner = new User(['role' => 'owner']);
        $administrator = new User(['role' => 'admin']);
        $session = (object) [
            'source_manifest' => json_encode([
                'content_safety' => [
                    'identification_documents_blocked' => false,
                    'sensitive_minor_images_blocked' => true,
                ],
            ], JSON_THROW_ON_ERROR),
        ];
        $historicalItem = (object) [
            'source_metadata' => json_encode([
                'content_safety' => [
                    'classification' => BatchContentSafety::HISTORICAL_IDENTIFICATION_DOCUMENT,
                    'document_year' => 1964,
                ],
            ], JSON_THROW_ON_ERROR),
        ];
        $boundaryItem = (object) [
            'source_metadata' => json_encode([
                'content_safety' => [
                    'classification' => BatchContentSafety::HISTORICAL_IDENTIFICATION_DOCUMENT,
                    'document_year' => 1965,
                ],
            ], JSON_THROW_ON_ERROR),
        ];

        $service->assertDecisionAllowed($session, $historicalItem, $owner, 'preserve_private');

        expect(fn () => $service->assertDecisionAllowed($session, $historicalItem, $administrator, 'preserve_private'))
            ->toThrow(ValidationException::class, 'owner')
            ->and(fn () => $service->assertDecisionAllowed($session, $boundaryItem, $owner, 'preserve_private'))
            ->toThrow(ValidationException::class, 'blocked for review');
    } finally {
        Carbon::setTestNow();
    }
});

it('enforces optional identification and sensitive-minor blocks without allowing private-preservation bypasses', function () {
    $service = app(BatchContentSafety::class);
    $owner = new User(['role' => 'owner']);
    $enabledSession = (object) [
        'source_manifest' => json_encode([
            'content_safety' => [
                'identification_documents_blocked' => true,
                'sensitive_minor_images_blocked' => true,
            ],
        ], JSON_THROW_ON_ERROR),
    ];
    $disabledSession = (object) [
        'source_manifest' => json_encode([
            'content_safety' => [
                'identification_documents_blocked' => false,
                'sensitive_minor_images_blocked' => false,
            ],
        ], JSON_THROW_ON_ERROR),
    ];
    $identityItem = (object) [
        'source_metadata' => json_encode([
            'content_safety' => [
                'classification' => BatchContentSafety::IDENTIFICATION_DOCUMENT,
            ],
        ], JSON_THROW_ON_ERROR),
    ];
    $sensitiveMinorItem = (object) [
        'source_metadata' => json_encode([
            'content_safety' => [
                'classification' => BatchContentSafety::SENSITIVE_MINOR_IMAGE,
            ],
        ], JSON_THROW_ON_ERROR),
    ];

    expect(fn () => $service->assertDecisionAllowed($enabledSession, $identityItem, $owner, 'approve_original'))
        ->toThrow(ValidationException::class, 'Identification-document blocking is enabled')
        ->and(fn () => $service->assertDecisionAllowed($enabledSession, $identityItem, $owner, 'preserve_private'))
        ->toThrow(ValidationException::class, 'Identification-document blocking is enabled')
        ->and(fn () => $service->assertDecisionAllowed($enabledSession, $sensitiveMinorItem, $owner, 'approve_original'))
        ->toThrow(ValidationException::class, 'Sensitive-minor-image blocking is enabled');

    $service->assertDecisionAllowed($disabledSession, $identityItem, $owner, 'approve_original');
    $service->assertDecisionAllowed($disabledSession, $sensitiveMinorItem, $owner, 'approve_original');
});
