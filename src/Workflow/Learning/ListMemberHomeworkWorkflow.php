<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Application\Service\MemberCheck;
use App\Context\Learning\Application\Service\PupilHomework;
use App\Context\Learning\Domain\ValueObject\VisibleTask;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\MemberRecords;
use App\Workflow\Learning\Model\ChildHomeworkList;
use App\Workflow\Learning\Model\OwnHomework;
use App\Workflow\Learning\Model\ParentHomeworkItems;
use App\Workflow\Learning\Model\ParentHomeworkOrder;
use Symfony\Component\Uid\Uuid;

final readonly class ListMemberHomeworkWorkflow
{
    public function __construct(
        private MemberCheck $member,
        private AccountLookup $accounts,
        private MemberRecords $records,
        private PupilHomework $homework,
        private StructureLookup $structure,
    ) {
    }

    public function __invoke(string $viewerId, string $state): ChildHomeworkList
    {
        $own = $this->ownOf($viewerId);
        $shown = ParentHomeworkOrder::shown($own->tasks(), $state);

        return new ChildHomeworkList($own->today->toString(), $this->itemsOf($shown, $viewerId)->listOf($shown));
    }

    private function ownOf(string $viewerId): OwnHomework
    {
        $record = $this->records->ownRecord($this->member->require($viewerId));

        return OwnHomework::of($record, $this->homework->of(OwnHomework::pupilsOf($record)));
    }

    /**
     * @param list<VisibleTask> $tasks
     */
    private function itemsOf(array $tasks, string $viewerId): ParentHomeworkItems
    {
        return ParentHomeworkItems::of(
            $this->structure->stages(ParentHomeworkItems::stageIdsOf($tasks)),
            $this->accounts->accounts(ParentHomeworkItems::reporterIdsOf($tasks)),
            Uuid::fromString($viewerId),
        );
    }
}
