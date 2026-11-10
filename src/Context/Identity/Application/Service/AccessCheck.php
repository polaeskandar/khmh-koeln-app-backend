<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\ValueObject\Access;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\NotFound;
use App\Platform\Access\Model\Group;
use App\Platform\Access\Model\GroupScope;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class AccessCheck
{
    private const string ASSIGNING_REFUSAL = 'assignments.notAllowed';

    public function __construct(
        private AuthorizationCheckerInterface $authorization,
        private GroupPermissions $groups,
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
        return $this->groups->scopeOfViewer($action, $resource);
    }

    public function requireIn(Action $action, Resource $resource, Group $group, string $missing): void
    {
        if ($this->scope($action, $resource)->covers($group)) {
            return;
        }

        throw NotFound::of($missing);
    }

    public function requireReaching(Action $action, Resource $resource, Uuid $serviceId, string $missing): void
    {
        if ($this->scope($action, $resource)->reaches($serviceId)) {
            return;
        }

        throw NotFound::of($missing);
    }

    public function requireChosen(Action $action, Resource $resource, ?Group $group, string $fieldPrefix): void
    {
        $scope = $this->scope($action, $resource);
        $chosen = $group === null ? [] : [$group];

        if ($scope->covers(...$chosen)) {
            return;
        }

        throw $group !== null && $scope->reaches($group->serviceId)
            ? InvalidInput::field($fieldPrefix . 'stageId', 'meetings.chooseStage')
            : InvalidInput::field($fieldPrefix . 'serviceId', 'meetings.chooseService');
    }

    public function requireAssigning(): GroupScope
    {
        $users = $this->groups->scopeOfViewer(Action::Update, Resource::Users);

        $assignable = $users->isChurchWide()
            ? $users
            : $this->groups->scopeOfViewer(Action::Update, Resource::Stages)->serviceWide();

        return $assignable->serviceIds() === []
            ? throw new AccessDeniedHttpException(self::ASSIGNING_REFUSAL)
            : $assignable;
    }

    public function requireAssigningIn(Group ...$groups): void
    {
        $outside = new ArrayCollection($groups)->filter(fn (Group $group): bool => !$this->requireAssigning()->covers(
            $group,
        ));

        if ($outside->isEmpty()) {
            return;
        }

        throw new AccessDeniedHttpException(self::ASSIGNING_REFUSAL);
    }
}
