<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Repository;

use App\Context\Identity\Domain\Entity\PasswordToken;

interface PasswordTokenRepositoryInterface
{
    public function replaceUnused(#[\SensitiveParameter] PasswordToken $token): void;
}
