<?php

declare(strict_types=1);

namespace App\Platform\Doctrine\Contract;

interface VersionChecks
{
    public function ensureCurrent(object $entity, ?int $version, string $refusal): void;
}
