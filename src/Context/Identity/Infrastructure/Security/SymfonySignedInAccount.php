<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Security;

use App\Context\Identity\Domain\Interface\SignedInAccountInterface;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;

final readonly class SymfonySignedInAccount implements SignedInAccountInterface
{
    public function __construct(
        private Security $security,
    ) {
    }

    #[Override]
    public function id(): ?Uuid
    {
        $account = $this->security->getUser();

        return $account instanceof SecurityUser ? Uuid::fromString($account->getUserIdentifier()) : null;
    }
}
