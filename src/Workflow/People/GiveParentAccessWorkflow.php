<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\ParentAccounts;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\ParentAccess;
use App\Context\People\Domain\ValueObject\AccessGrant;
use App\Library\Time\Client\Calendar;
use App\Workflow\People\Model\ParentAccessItem;
use App\Workflow\People\Model\ParentAccessItems;
use Doctrine\Common\Collections\ArrayCollection;
use LogicException;

final readonly class GiveParentAccessWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ParentAccounts $accounts,
        private ParentAccess $parentAccess,
        private StructureLookup $structure,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(
        string $userId,
        string $personId,
        string $relationship,
        string $approvedBy,
    ): ParentAccessItem {
        $this->access->require(Action::Update, Resource::Users, refusal: 'guardians.adminOnlyGrant');

        $parent = $this->accounts->active($userId);
        $child = $this->parentAccess->child($personId);
        $grant = new AccessGrant($parent->id(), $parent->profile()->displayName->value, $relationship, $approvedBy);

        $link = $this->parentAccess->grant($grant, $child, alongside: fn () => $this->accounts->giveParentRole(
            $parent,
        ));

        $items = ParentAccessItems::lookedUpIn(
            $this->accounts,
            $this->parentAccess,
            $this->structure,
            $this->calendar,
            new ArrayCollection([$link]),
        );

        return $items[0] ?? throw new LogicException('A granted link has its parent and child.');
    }
}
