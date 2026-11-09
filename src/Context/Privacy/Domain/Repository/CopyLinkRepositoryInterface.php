<?php

declare(strict_types=1);

namespace App\Context\Privacy\Domain\Repository;

use App\Context\Privacy\Domain\Entity\CopyLink;
use App\Context\Privacy\Domain\Entity\DataRequest;
use DateTimeImmutable;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

interface CopyLinkRepositoryInterface
{
    public function replaceUnused(CopyLink $issued): void;

    /**
     * @template T
     *
     * @param callable(DataRequest): T $use
     *
     * @return T|null
     */
    public function spend(#[SensitiveParameter] string $tokenHash, DateTimeImmutable $at, callable $use): mixed;

    public function latestOf(Uuid $requestId): ?CopyLink;
}
