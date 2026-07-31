<?php

namespace App\Console\Commands;

use App\Domain\Storage\Services\WasabiLeastPrivilegePolicy;
use Illuminate\Console\Command;

final class GenerateWasabiPolicyCommand extends Command
{
    protected $signature = 'archive:wasabi-policy {bucket : Existing private Wasabi bucket name}';

    protected $description = 'Print the least-privilege policy for the FamilyArchive Wasabi application user';

    public function handle(WasabiLeastPrivilegePolicy $policy): int
    {
        $this->line($policy->json((string) $this->argument('bucket')));

        return self::SUCCESS;
    }
}
