<?php

declare(strict_types=1);

namespace App\Context\Access\Infrastructure\Security;

use App\Context\Access\Domain\Interface\ViewerInterface;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;

final readonly class SymfonyViewer implements ViewerInterface
{
    public function __construct(
        private Security $security,
    ) {
    }

    #[Override]
    public function id(): ?Uuid
    {
        $identifier = $this->security->getUser()?->getUserIdentifier();

        return $identifier === null || !Uuid::isValid($identifier) ? null : Uuid::fromString($identifier);
    }
}
