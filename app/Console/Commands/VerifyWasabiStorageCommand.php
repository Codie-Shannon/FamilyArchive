<?php

namespace App\Console\Commands;

use App\Domain\Storage\Exceptions\WasabiProviderException;
use App\Domain\Storage\Services\WasabiConnectionVerifier;
use Illuminate\Console\Command;

final class VerifyWasabiStorageCommand extends Command
{
    protected $signature = 'archive:wasabi-verify';

    protected $description = 'Verify private Wasabi access, bucket protection, versioned write/readback and health cleanup';

    public function handle(WasabiConnectionVerifier $verifier): int
    {
        $this->components->info('Checking the private Wasabi production-storage boundary.');

        try {
            $verification = $verifier->verify();
        } catch (WasabiProviderException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info($verification->safe_summary);
        $this->line('Credentials, bucket identity, endpoint, object keys and version IDs were not displayed.');

        return self::SUCCESS;
    }
}
