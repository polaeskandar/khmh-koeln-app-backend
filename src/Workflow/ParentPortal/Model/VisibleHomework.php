<?php

declare(strict_types=1);

namespace App\Workflow\ParentPortal\Model;

use App\Context\Learning\Domain\ValueObject\Pupil;
use App\Context\Learning\Domain\ValueObject\PupilTasks;
use App\Context\Learning\Domain\ValueObject\VisibleTask;
use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\ValueObject\Family;
use App\Library\Problem\Exception\NotFound;
use App\Library\Strings\Model\InputText;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

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

    public function of(string $childId): ?ChildHomework
    {
        $id = InputText::of($childId)->lowered()->toString();

        $matching = new ArrayCollection($this->children)->filter(
            static fn (ChildHomework $child): bool => $child->child->id()->toRfc4122() === $id,
        );

        return $matching->getValues()[0] ?? null;
    }

    public function requireChild(string $childId): ChildHomework
    {
        return $this->of($childId) ?? throw NotFound::of('person');
    }

    public function requireTask(string $childId, string $homeworkId): VisibleTask
    {
        $id = InputText::of($homeworkId)->lowered()->toString();

        $matching = new ArrayCollection($this->requireChild($childId)->tasks->tasks)->filter(
            static fn (VisibleTask $visible): bool => $visible->task->id()->toRfc4122() === $id,
        );

        return $matching->getValues()[0] ?? throw NotFound::coded('homework.notAvailable');
    }

    public function missingTask(string $childId): never
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
