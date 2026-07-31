<?php

namespace App\Console\Commands;

use App\Domain\Operations\Exceptions\ProductionVerificationException;
use App\Domain\Operations\Services\ProductionDeploymentVerifier;
use Illuminate\Console\Command;

final class VerifyProductionDeploymentCommand extends Command
{
    protected $signature = 'archive:production-verify';

    protected $description = 'Verify the live production boundary without displaying infrastructure identifiers';

    public function handle(ProductionDeploymentVerifier $verifier): int
    {
        try {
            $verifier->verify();
        } catch (ProductionVerificationException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(
            'Live HTTPS, durable application state, response hardening and private archive storage verification passed.'
        );
        $this->line('No hostname, provider account, bucket, object key, version identifier or credential was displayed.');

        return self::SUCCESS;
    }
}
