<?php

declare(strict_types=1);

namespace App\Interface\Application\Security\Service;

use App\Context\Identity\Domain\ValueObject\Access;
use App\Interface\Application\Security\Model\RequiresAccess;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[AsEventListener]
final readonly class AccessCheck
{
    public function __construct(
        private AuthorizationCheckerInterface $authorization,
    ) {
    }

    public function __invoke(ControllerArgumentsEvent $event): void
    {
        $required = new ArrayCollection($event->getAttributes(RequiresAccess::class));

        if ($required->filter($this->isRefused(...))->isEmpty()) {
            return;
        }

        throw new AccessDeniedHttpException('forbidden');
    }

    private function isRefused(RequiresAccess $requirement): bool
    {
        return !$this->authorization->isGranted(Access::ATTRIBUTE, $requirement->access());
    }
}
