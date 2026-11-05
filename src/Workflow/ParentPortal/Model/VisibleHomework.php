<?php

declare(strict_types=1);

namespace App\Workflow\ParentPortal\Model;

use App\Context\Learning\Domain\ValueObject\Pupil;
use App\Context\Learning\Domain\ValueObject\PupilTasks;
use App\Context\Learning\Domain\ValueObject\VisibleTask;
use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\ValueObject\Family;
use App\Library\Collections\Model\Collections;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class VisibleHomework
{
    /**
     * @param list<ChildHomework> $children
     */
    private function __construct(
        public LocalDate $today,
        public array $children,
    ) {
    }

    public static function none(Family $family): self
    {
        return new self($family->today, []);
    }

    /**
     * @return list<Pupil>
     */
    public static function pupilsOf(Family $family): array
    {
        $pupilOf = static fn (Person $child): Pupil => new Pupil($child->id(), $family->stageIdsOf($child));

        return new ArrayCollection($family->children)->map($pupilOf)->getValues();
    }

    /**
     * @param list<PupilTasks> $tasks
     */
    public static function forFamily(Family $family, array $tasks): self
    {
        return new self(
            $family->today,
            self::childrenOf($family, Collections::firstPerKey(
                new ArrayCollection($tasks),
                keyOf: self::pupilKey(...),
            )),
        );
    }

    public function of(Uuid $childId): ?ChildHomework
    {
        $found = new ArrayCollection($this->children)
            ->filter(static fn (ChildHomework $child): bool => $child->child->id()->equals($childId))
            ->first();

        return $found === false ? null : $found;
    }

    public function task(Uuid $childId, Uuid $homeworkId): ?VisibleTask
    {
        return $this->of($childId)?->tasks->task($homeworkId);
    }

    /**
     * @return ReadableCollection<int, Membership>
     */
    public function memberships(): ReadableCollection
    {
        return new ArrayCollection($this->children)->reduce(
            /**
             * @param ArrayCollection<int, Membership> $all
             *
             * @return ArrayCollection<int, Membership>
             */
            static fn (ArrayCollection $all, ChildHomework $child): ArrayCollection => new ArrayCollection([
                ...$all->getValues(),
                ...$child->memberships->getValues(),
            ]),
            initial: new ArrayCollection(),
        );
    }

    /**
     * @param ArrayCollection<string, PupilTasks> $tasks
     *
     * @return list<ChildHomework>
     */
    private static function childrenOf(Family $family, ArrayCollection $tasks): array
    {
        return Collections::mapPresent(
            new ArrayCollection($family->children),
            static fn (Person $child): ?ChildHomework => self::childOf($family, $child, $tasks),
        )->getValues();
    }

    /**
     * @param ArrayCollection<string, PupilTasks> $tasks
     */
    private static function childOf(Family $family, Person $child, ArrayCollection $tasks): ?ChildHomework
    {
        $own = $tasks->get($child->id()->toRfc4122());

        return $own === null ? null : new ChildHomework($child, $family->membershipsOf($child), $own);
    }

    private static function pupilKey(PupilTasks $tasks): string
    {
        return $tasks->personId->toRfc4122();
    }
}
