<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Repository;

use App\Context\Identity\Domain\Entity\ApiToken;
use DateTimeImmutable;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

interface ApiTokenRepositoryInterface
{
    public function save(ApiToken $issued): void;

    public function holderOf(#[SensitiveParameter] string $tokenHash, DateTimeImmutable $now): ?Uuid;

    public function revoke(#[SensitiveParameter] string $tokenHash, DateTimeImmutable $at): void;

    public function revokeAllOf(Uuid $userId, DateTimeImmutable $at): void;
}
