<?php

namespace App\Domain\Operations\Contracts;

interface ProductionProbe
{
    /**
     * @return array{
     *     https_response: bool,
     *     database: bool,
     *     cache: bool,
     *     security_headers: bool
     * }
     */
    public function run(string $applicationUrl): array;
}
