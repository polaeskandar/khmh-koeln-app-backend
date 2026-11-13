<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Entity\StaffAssignment;
use App\Context\Organization\Domain\Repository\StaffAssignmentRepositoryInterface;
use App\Context\Organization\Domain\ValueObject\ChosenPlacement;
use App\Library\Problem\Exception\RuleViolation;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Uid\Uuid;

final readonly class AssignmentChanges
{
    public function __construct(
        private StaffAssignmentRepositoryInterface $assignments,
    ) {
    }

    public function assign(Uuid $accountId, string $accountName, ChosenPlacement $group): StaffAssignment
    {
        $covering = $this->findCovering($accountId, $group);

        if ($covering !== null) {
            throw self::alreadyAssigned($accountName, new ChosenPlacement($covering->service(), $covering->stage()));
        }

        try {
            return $this->store($accountId, $group);
        } catch (UniqueConstraintViolationException) {
            throw self::alreadyAssigned($accountName, $group);
        }
    }

    public function remove(StaffAssignment $assignment): void
    {
        $this->assignments->remove($assignment);
    }

    private function store(Uuid $accountId, ChosenPlacement $group): StaffAssignment
    {
        if ($group->stage === null) {
            $this->assignments->removeStagesOf($accountId, $group->service->id());
        }

        $assignment = new StaffAssignment($accountId, $group->service, $group->stage);
        $this->assignments->add($assignment);

        return $assignment;
    }

    private function findCovering(Uuid $accountId, ChosenPlacement $group): ?StaffAssignment
    {
        return $this->assignments->findCovering($accountId, $group->service->id(), $group->stage?->id());
    }

    private static function alreadyAssigned(string $accountName, ChosenPlacement $group): RuleViolation
    {
        $where = $group->stage?->name()->value ?? $group->service->name()->value;

        return new RuleViolation('assignments.alreadyAssigned', ['name' => $accountName, 'where' => $where]);
    }
}
