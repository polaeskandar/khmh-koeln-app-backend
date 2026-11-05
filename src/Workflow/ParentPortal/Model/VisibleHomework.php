<?php

declare(strict_types=1);

namespace App\Workflow\ParentPortal\Model;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Learning\Application\Service\PupilHomework;
use App\Context\Learning\Domain\ValueObject\VisibleTask;
use App\Context\People\Application\Service\ParentChildren;
use App\Context\People\Domain\Entity\Membership;
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

    public static function lookedUpIn(
        AccountLookup $accounts,
        ParentChildren $families,
        PupilHomework $homework,
        Uuid $viewerId,
    ): self {
        $family = $families->of($viewerId);

        if ($accounts->account($viewerId)?->status()->isActive() !== true) {
            return new self($family->today, []);
        }

        return new self($family->today, ChildHomeworks::lookedUpIn($family, $homework));
    }

    public function of(Uuid $childId): ?ChildHomework
    {
        $found = new ArrayCollection($this->children)
            ->filter(static fn (ChildHomework $child): bool => $child->child->id()->equals($childId))
            ->first();

        return $found === false ? null : $found;
    }

    public function requireChild(Uuid $childId): ChildHomework
    {
        return $this->of($childId) ?? throw NotFound::of('person');
    }

    public function requireTask(Uuid $childId, Uuid $homeworkId): VisibleTask
    {
        return $this->requireChild($childId)->tasks->task($homeworkId) ?? throw self::notAvailable();
    }

    public function missingTask(Uuid $childId): never
    {
        $this->requireChild($childId);

        throw self::notAvailable();
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

    private static function notAvailable(): NotFound
    {
        return NotFound::coded('homework.notAvailable');
    }
}
