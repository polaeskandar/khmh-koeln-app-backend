<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Application\Service\MemberCheck;
use App\Context\Learning\Application\Service\PupilHomework;
use App\Context\Learning\Domain\Enum\HomeworkFilter;
use App\Context\Learning\Domain\ValueObject\VisibleTask;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\MemberRecords;
use App\Library\Problem\Exception\InvalidInput;
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
        $viewer = Uuid::fromString($viewerId);
        $own = $this->ownOf($viewer);
        $filter = HomeworkFilter::tryFrom($state) ?? throw InvalidInput::field('state', 'validation.invalidChoice');
        $shown = ParentHomeworkOrder::shown($own->tasks(), $filter);

        return new ChildHomeworkList($own->today->toString(), $this->itemsOf($shown, $viewer)->listOf($shown));
    }

    private function ownOf(Uuid $viewer): OwnHomework
    {
        $record = $this->records->getOwnRecord($this->member->require($viewer));

        return OwnHomework::of($record, $this->homework->listTasksOf(OwnHomework::pupilsOf($record)));
    }

    /**
     * @param list<VisibleTask> $tasks
     */
    private function itemsOf(array $tasks, Uuid $viewer): ParentHomeworkItems
    {
        return ParentHomeworkItems::of(
            $this->structure->listStages(ParentHomeworkItems::stageIdsOf($tasks)),
            $this->accounts->listByIds(ParentHomeworkItems::reporterIdsOf($tasks)),
            $viewer,
        );
    }
}
