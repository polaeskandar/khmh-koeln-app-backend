<?php

declare(strict_types=1);

namespace App\Workflow\MemberPortal;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Application\Service\MemberCheck;
use App\Context\Learning\Application\Service\PupilHomework;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\MemberRecords;
use App\Workflow\MemberPortal\Model\OwnHomework;
use App\Workflow\ParentPortal\Model\ChildHomeworkList;
use App\Workflow\ParentPortal\Model\ParentHomeworkItems;
use App\Workflow\ParentPortal\Model\ParentHomeworkOrder;
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
        $own = OwnHomework::lookedUpIn($this->records, $this->homework, $this->member->require($viewerId));
        $shown = ParentHomeworkOrder::shown($own->tasks(), $state);

        $items = ParentHomeworkItems::lookedUpIn(
            $this->structure,
            $this->accounts,
            $shown,
            Uuid::fromString($viewerId),
        );

        return new ChildHomeworkList($own->today->toString(), $items->listOf($shown));
    }
}
