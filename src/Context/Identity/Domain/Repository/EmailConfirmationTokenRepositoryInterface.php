<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Repository;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\Identity\Domain\Entity\EmailConfirmationToken;
use DateTimeImmutable;
use SensitiveParameter;

interface EmailConfirmationTokenRepositoryInterface
{
    public function replaceUnused(EmailConfirmationToken $issued): void;

    /**
     * @param callable(Account): void $use
     */
    public function spend(#[SensitiveParameter] string $tokenHash, DateTimeImmutable $at, callable $use): bool;
}
