<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

use App\Library\Collections\Model\Collections;
use App\Library\Strings\Model\Text;
use App\Workflow\Learning\Model\ChildHomework;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class ChildSummary
{
    public function __construct(
        public ChildName $name,
        public ?PersonGroup $group,
        public int $toDo,
        public int $done,
    ) {
    }

    /**
     * @param list<ChildHomework> $children
     *
     * @return list<self>
     */
    public static function listOf(array $children, Placements $placements): array
    {
        return new ArrayCollection($children)->map(
            static fn (ChildHomework $child): self => self::of($child, $placements),
        )->getValues();
    }

    public static function of(ChildHomework $child, Placements $placements): self
    {
        $groups = Collections::sortedBy(
            new ArrayCollection($placements->groupsOf($child->memberships)),
            keyOf: self::groupKey(...),
        );

        return new self(
            ChildName::of($child->child),
            $groups[0] ?? null,
            $child->tasks->countToDo(),
            $child->tasks->countDone(),
        );
    }

    private static function groupKey(PersonGroup $group): string
    {
        return Text::format('%s|%04d', $group->service->name, $group->stage->position ?? 0)->toString();
    }
}
