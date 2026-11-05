<?php

declare(strict_types=1);

namespace App\Workflow\MemberPortal\Model;

final readonly class MemberGroup
{
    /**
     * @param list<GroupSchedule> $schedules
     */
    public function __construct(
        public GroupPlace $place,
        public array $schedules,
        public ?LastLesson $lastLesson,
    ) {
    }
}
