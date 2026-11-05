<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\ValueObject\Access;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final readonly class AccessCheck
{
    public function __construct(
        private AuthorizationCheckerInterface $authorization,
    ) {
    }

    public function require(Action $action, Resource $resource): void
    {
        if ($this->authorization->isGranted(Access::ATTRIBUTE, new Access($action, $resource))) {
            return;
        }

        throw new AccessDeniedHttpException('forbidden');
    }
}
