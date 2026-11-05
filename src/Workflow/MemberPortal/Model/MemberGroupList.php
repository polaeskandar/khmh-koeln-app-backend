<?php

declare(strict_types=1);

namespace App\Workflow\MemberPortal\Model;

use App\Context\Learning\Domain\ValueObject\TaughtLesson;
use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\People\Domain\Entity\Membership;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class MemberGroupList
{
    /**
     * @param list<MemberGroup> $items
     */
    private function __construct(
        public string $today,
        public array $items,
    ) {
    }

    /**
     * @param ReadableCollection<int, Schedule> $schedules
     * @param list<GroupServant> $servants
     * @param array<string, TaughtLesson> $lessons
     */
    public static function of(
        MemberRoster $roster,
        ReadableCollection $schedules,
        array $servants,
        array $lessons,
    ): self {
        $groupOf = static fn (Membership $membership): MemberGroup => new MemberGroup(
            GroupPlace::of($membership, $roster->groups),
            $schedules
                ->filter(static fn (Schedule $schedule): bool => RosterSeats::isOwnRoster(
                    $membership,
                    $schedule->roster(),
                ))
                ->map(GroupSchedule::of(...))
                ->getValues(),
            $servants,
            LastLesson::of($lessons[$membership->stageId()?->toRfc4122() ?? ''] ?? null),
        );

        return new self(
            $roster->today->toString(),
            new ArrayCollection($roster->ordered())->map($groupOf)->getValues(),
        );
    }
}
