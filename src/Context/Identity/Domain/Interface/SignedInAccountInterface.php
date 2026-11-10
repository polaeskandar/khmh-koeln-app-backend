<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Interface;

use Symfony\Component\Uid\Uuid;

interface SignedInAccountInterface
{
    public function id(): ?Uuid;
}
