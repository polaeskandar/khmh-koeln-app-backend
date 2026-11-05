<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Repository\PasswordTokenRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class Credentials
{
    public function __construct(
        private PasswordTokenRepositoryInterface $passwordTokens,
        private ApiTokens $apiTokens,
    ) {
    }

    public function endAllOf(Uuid $userId): void
    {
        $this->passwordTokens->forgetUnusedOf($userId);
        $this->apiTokens->revokeAllOf($userId);
    }
}
