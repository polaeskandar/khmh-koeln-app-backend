<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Entity\StaffAssignment;
use App\Context\Organization\Domain\Repository\RegistrationServiceRepositoryInterface;
use App\Context\Organization\Domain\Repository\StaffAssignmentRepositoryInterface;
use App\Context\Organization\Domain\ValueObject\ChosenPlacement;
use App\Library\Problem\Exception\RuleViolation;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Uid\Uuid;

final readonly class AssignmentChanges
{
    public function __construct(
        private StaffAssignmentRepositoryInterface $assignments,
        private AssignmentChoices $choices,
        private RegistrationServiceRepositoryInterface $registrations,
    ) {
    }

    /**
     * @param list<array{serviceId: ?string, stageId: ?string}> $choices
     *
     * @return ReadableCollection<int, ChosenPlacement> each group once
     */
    public function chosen(array $choices, string $field): ReadableCollection
    {
        return $this->choices->of($choices, $field);
    }

    /**
     * @param ReadableCollection<int, ChosenPlacement> $groups
     */
    public function settle(Uuid $accountId, ReadableCollection $groups): void
    {
        $this->registrations->removeFor($accountId);

        $new = $groups->filter(fn (ChosenPlacement $group): bool => !$this->isHeld($accountId, $group));

        foreach ($new as $group) {
            $this->assignments->add(new StaffAssignment($accountId, $group->service, $group->stage));
        }
    }

    public function assign(Uuid $accountId, string $accountName, ChosenPlacement $group): StaffAssignment
    {
        if ($this->isHeld($accountId, $group)) {
            throw self::alreadyAssigned($accountName, $group);
        }

        $assignment = new StaffAssignment($accountId, $group->service, $group->stage);

        try {
            $this->assignments->add($assignment);
        } catch (UniqueConstraintViolationException) {
            throw self::alreadyAssigned($accountName, $group);
        }

        return $assignment;
    }

    public function remove(StaffAssignment $assignment): void
    {
        $this->assignments->remove($assignment);
    }

    private function isHeld(Uuid $accountId, ChosenPlacement $group): bool
    {
        return $this->assignments->findHeld($accountId, $group->service->id(), $group->stage?->id()) !== null;
    }

    private static function alreadyAssigned(string $accountName, ChosenPlacement $group): RuleViolation
    {
        $where = $group->stage?->name()->value ?? $group->service->name()->value;

        return new RuleViolation('assignments.alreadyAssigned', ['name' => $accountName, 'where' => $where]);
    }
}
