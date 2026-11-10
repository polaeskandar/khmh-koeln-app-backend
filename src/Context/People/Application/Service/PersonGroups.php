<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Repository\PersonGroupRepositoryInterface;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Client\Calendar;
use App\Platform\Access\Model\Group;
use App\Platform\Access\Model\GroupScope;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\NilUuid;
use Symfony\Component\Uid\Uuid;

final readonly class PersonGroups
{
    public function __construct(
        private PersonGroupRepositoryInterface $groups,
        private Calendar $calendar,
    ) {
    }

    public function requireVisible(string $personId, GroupScope $scope): void
    {
        $id = Uuid::isValid($personId) ? Uuid::fromString($personId) : new NilUuid();

        $open = new ArrayCollection($this->groups->openOn($id, $this->calendar->today()))->map(
            static fn (array $group): Group => new Group(
                Uuid::fromString($group['serviceId']),
                $group['stageId'] === null ? null : Uuid::fromString($group['stageId']),
            ),
        );

        if ($scope->covers(...$open->getValues())) {
            return;
        }

        throw NotFound::of('person');
    }

    public static function groupOf(Membership $membership): Group
    {
        return new Group($membership->serviceId(), $membership->stageId());
    }
}
