<?php

declare(strict_types=1);

namespace App\Workflow\MemberPortal\Model;

final readonly class MemberGroup
{
    /**
     * @param list<GroupSchedule> $schedules
     * @param list<GroupServant> $servants
     */
    public function __construct(
        public GroupPlace $place,
        public array $schedules,
        public array $servants,
        public ?LastLesson $lastLesson,
    ) {
    }
}
