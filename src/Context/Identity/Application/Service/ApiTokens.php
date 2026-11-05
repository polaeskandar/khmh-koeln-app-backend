<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Repository\ApiTokenRepositoryInterface;
use App\Library\Checksum\Model\Checksum;
use App\Library\Time\Client\Calendar;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

final readonly class ApiTokens
{
    public function __construct(
        private ApiTokenRepositoryInterface $tokens,
        private Calendar $calendar,
    ) {
    }

    public function revoke(#[SensitiveParameter] string $token): void
    {
        $this->tokens->revoke(Checksum::sha256($token), $this->calendar->now()->toDateTimeImmutable());
    }

    public function revokeAllOf(Uuid $userId): void
    {
        $this->tokens->revokeAllOf($userId, $this->calendar->now()->toDateTimeImmutable());
    }
}
