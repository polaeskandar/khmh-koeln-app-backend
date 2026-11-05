<?php

declare(strict_types=1);

namespace App\Workflow\Demo;

use App\Context\Identity\Application\Service\DemoAccounts;
use App\Context\Meetings\Application\Service\DemoSchedules;
use App\Context\Organization\Application\Service\DemoStructure;
use App\Context\People\Application\Service\DemoPeople;
use App\Platform\Doctrine\Contract\DemoDatabase;
use App\Workflow\Demo\Model\DemoGroup;

final readonly class SeedDemoDataWorkflow
{
    public function __construct(
        private DemoDatabase $database,
        private DemoAccounts $accounts,
        private DemoStructure $structure,
        private DemoPeople $people,
        private DemoSchedules $schedules,
    ) {
    }

    public function __invoke(): void
    {
        $this->database->replace(function (): void {
            $this->accounts->seed();

            foreach (DemoGroup::listOf($this->structure->seed()) as $group) {
                $this->people->seed($group->name, $group->serviceId, $group->stageId);
                $this->schedules->seed($group->name, $group->serviceId, $group->stageId);
            }
        });
    }
}
