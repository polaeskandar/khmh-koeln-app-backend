<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Entity\StaffAssignment;
use App\Context\Organization\Domain\Repository\RegistrationServiceRepositoryInterface;
use App\Context\Organization\Domain\Repository\StaffAssignmentRepositoryInterface;
use App\Context\Organization\Domain\ValueObject\ChosenPlacement;
use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\RuleViolation;
use Closure;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Uid\Uuid;

final readonly class AssignmentChanges
{
    private const int MAX_CHOSEN = 50;

    public function __construct(
        private StaffAssignmentRepositoryInterface $assignments,
        private PlacementChoices $placements,
        private RegistrationServiceRepositoryInterface $registrations,
    ) {
    }

    /**
     * @param list<array{serviceId: ?string, stageId: ?string}> $choices
     *
     * @return ReadableCollection<int, ChosenPlacement> each group once, without the stages of a service chosen as a
     *     whole
     */
    public function chosen(array $choices, string $field): ReadableCollection
    {
        $listed = new ArrayCollection($choices);

        if ($listed->count() > self::MAX_CHOSEN) {
            throw InvalidInput::field($field, 'validation.tooMany', ['max' => self::MAX_CHOSEN]);
        }

        $pick =
            /** @param array{serviceId: ?string, stageId: ?string} $choice */
            fn (array $choice, int $index): ChosenPlacement => $this->placements->chosen(
                $choice['serviceId'],
                $choice['stageId'],
                fieldPrefix: $field . '.' . $index . '.',
            );

        InvalidInput::all(...Collections::mapArray(
            $choices,
            /** @param array{serviceId: ?string, stageId: ?string} $choice */
            static fn (array $choice, int $index): Closure => static fn (): ChosenPlacement => $pick($choice, $index),
        ));

        return self::withoutCovered(Collections::firstPerKey(
            new ArrayCollection(Collections::mapArray($choices, $pick)),
            keyOf: self::keyOf(...),
        ));
    }

    /**
     * @param ReadableCollection<int, ChosenPlacement> $groups
     */
    public function settle(Uuid $accountId, ReadableCollection $groups): void
    {
        $this->registrations->removeFor($accountId);

        $new = $groups->filter(fn (ChosenPlacement $group): bool => $this->findCovering($accountId, $group) === null);

        foreach ($new as $group) {
            $this->store($accountId, $group);
        }
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

    /**
     * @template TKey of array-key
     *
     * @param ReadableCollection<TKey, ChosenPlacement> $groups
     *
     * @return ReadableCollection<int, ChosenPlacement>
     */
    private static function withoutCovered(ReadableCollection $groups): ReadableCollection
    {
        $uncovered = $groups->filter(
            static fn (ChosenPlacement $group): bool => $groups->filter($group->isCoveredBy(...))->isEmpty(),
        );

        return new ArrayCollection($uncovered->getValues());
    }

    /**
     * @return non-empty-string
     */
    private static function keyOf(ChosenPlacement $group): string
    {
        return $group->service->id()->toRfc4122() . '/' . ($group->stage?->id()->toRfc4122() ?? '');
    }
}
