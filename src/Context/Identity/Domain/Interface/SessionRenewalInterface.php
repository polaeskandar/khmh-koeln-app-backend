<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Interface;

interface SessionRenewalInterface
{
    public function renew(): void;
}
