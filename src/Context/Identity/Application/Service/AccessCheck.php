<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\ValueObject\Access;
use App\Platform\Access\Model\Group;
use App\Platform\Access\Model\GroupScope;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class AccessCheck
{
    public function __construct(
        private AuthorizationCheckerInterface $authorization,
        private GroupPermissions $groups,
        private AssignmentRights $assignments,
    ) {
    }

    public function allows(Action $action, Resource $resource): bool
    {
        return $this->authorization->isGranted(Access::ATTRIBUTE, new Access($action, $resource));
    }

    public function require(Action $action, Resource $resource, string $refusal = 'forbidden'): void
    {
        if ($this->allows($action, $resource)) {
            return;
        }

        throw new AccessDeniedHttpException($refusal);
    }

    public function scope(Action $action, Resource $resource, string $refusal = 'forbidden'): GroupScope
    {
        $this->require($action, $resource, $refusal);

        return $this->scopeOf($action, $resource);
    }

    public function scopeOf(Action $action, Resource $resource): GroupScope
    {
        return $this->groups->ofViewer($action, $resource);
    }

    public function requireIn(Action $action, Resource $resource, Group $group, string $missing): void
    {
        GroupRefusals::requireIn($this->scope($action, $resource), $group, $missing);
    }

    public function requireReaching(Action $action, Resource $resource, Uuid $serviceId, string $missing): void
    {
        GroupRefusals::requireReached($this->scope($action, $resource), $serviceId, $missing);
    }

    public function requireChosen(Action $action, Resource $resource, ?Group $group, string $fieldPrefix): void
    {
        GroupChoiceRefusals::requireChosen($this->scope($action, $resource), $group, $fieldPrefix);
    }

    public function requireAssigning(): GroupScope
    {
        return $this->assignments->require();
    }

    public function requireAssigningIn(Group ...$groups): void
    {
        $this->assignments->requireIn(...$groups);
    }
}
