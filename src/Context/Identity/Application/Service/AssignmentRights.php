<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Platform\Access\Model\Group;
use App\Platform\Access\Model\GroupScope;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class AssignmentRights
{
    private const string REFUSAL = 'assignments.notAllowed';

    public function __construct(
        private GroupPermissions $groups,
    ) {
    }

    public function require(): GroupScope
    {
        $users = $this->groups->scopeOfViewer(Action::Update, Resource::Users);

        $assignable = $users->isChurchWide()
            ? $users
            : $this->groups->scopeOfViewer(Action::Update, Resource::Stages)->serviceWide();

        return $assignable->serviceIds() === [] ? throw new AccessDeniedHttpException(self::REFUSAL) : $assignable;
    }

    public function requireIn(Group ...$groups): void
    {
        $outside = new ArrayCollection($groups)->filter(fn (Group $group): bool => !$this->require()->covers($group));

        if ($outside->isEmpty()) {
            return;
        }

        throw new AccessDeniedHttpException(self::REFUSAL);
    }
}
