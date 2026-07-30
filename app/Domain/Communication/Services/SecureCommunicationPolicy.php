<?php

namespace App\Domain\Communication\Services;

use InvalidArgumentException;

final class SecureCommunicationPolicy
{
    /** @param array{protocol_version?: int, ciphertext?: string, encrypted_content_key?: string, content_digest?: string} $envelope */
    public function validateEnvelope(array $envelope): void
    {
        if (($envelope['protocol_version'] ?? null) !== (int) config('communication_bridges.end_to_end_encryption.protocol_version')) {
            throw new InvalidArgumentException('Unsupported encrypted envelope protocol.');
        }

        foreach (['ciphertext', 'encrypted_content_key', 'content_digest'] as $field) {
            if (blank($envelope[$field] ?? null)) {
                throw new InvalidArgumentException("Encrypted envelope is missing {$field}.");
            }
        }

        if (! preg_match('/\A[a-f0-9]{64}\z/i', $envelope['content_digest'])) {
            throw new InvalidArgumentException('Encrypted envelope digest must be hexadecimal SHA-256.');
        }
    }

    /** @return array<string, array<string, bool|string>> */
    public function bridgeReadiness(): array
    {
        return config('communication_bridges.providers');
    }
}
