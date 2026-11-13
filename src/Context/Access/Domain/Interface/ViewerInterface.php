<?php

declare(strict_types=1);

namespace App\Context\Access\Domain\Interface;

use Symfony\Component\Uid\Uuid;

interface ViewerInterface
{
    public function id(): ?Uuid;
}
