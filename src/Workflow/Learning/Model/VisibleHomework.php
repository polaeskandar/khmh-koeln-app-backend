<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\ValueObject\Pupil;
use App\Context\Learning\Domain\ValueObject\PupilTasks;
use App\Context\Learning\Domain\ValueObject\VisibleTask;
use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\ValueObject\Family;
use App\Library\Problem\Exception\NotFound;
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
        return new self($family->today, ChildHomeworks::of($family, $tasks));
    }

    public function of(Uuid $childId): ?ChildHomework
    {
        $matching = new ArrayCollection($this->children)->filter(
            static fn (ChildHomework $child): bool => $child->child->id()->equals($childId),
        );

        return $matching->getValues()[0] ?? null;
    }

    public function requireChild(Uuid $childId): ChildHomework
    {
        return $this->of($childId) ?? throw NotFound::of('person');
    }

    public function requireTask(Uuid $childId, ?Uuid $homeworkId): VisibleTask
    {
        $tasks = $this->requireChild($childId)->tasks;
        $task = $homeworkId === null ? null : $tasks->task($homeworkId);

        return $task ?? throw NotFound::coded('homework.notAvailable');
    }

    public function missingTask(Uuid $childId): never
    {
        $this->requireChild($childId);

        throw NotFound::coded('homework.notAvailable');
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
}
