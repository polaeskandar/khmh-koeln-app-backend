<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Application\Service\RoleHolders;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Learning\Application\Service\StageLessons;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Enum\LessonTab;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\Scheduling\Application\Service\MeetingLookup;
use App\Context\Scheduling\Domain\ValueObject\ArchivedStructure;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Model\Ids;
use App\Platform\Access\Model\Group;
use App\Workflow\Identity\Model\AccountReference;
use App\Workflow\Identity\Model\AccountReferences;
use App\Workflow\Learning\Model\LessonList;
use App\Workflow\Learning\Model\LessonRows;
use App\Workflow\Learning\Model\LessonStageView;
use App\Workflow\Learning\Model\MeetingViews;
use App\Workflow\Learning\Model\NoMeeting;
use App\Workflow\Learning\Model\OffScheduleMeeting;
use App\Workflow\Learning\Model\PlannedMeeting;
use App\Workflow\Learning\Model\SessionMeeting;
use App\Workflow\Organization\Model\StructureGroup;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class ListLessonsWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StructureLookup $structure,
        private StageLessons $lessons,
        private MeetingLookup $meetings,
        private AccountLookup $accounts,
        private RoleHolders $holders,
    ) {
    }

    public function __invoke(string $stageId, string $tab, ?string $query): LessonList
    {
        $this->access->require(Action::Read, Resource::Lessons);

        $stage = $this->structure->getStage(Ids::toUuid($stageId, notFound: 'stage'));
        $group = StructureGroup::ofStage($stage);
        $this->access->requireIn(Action::Read, Resource::Lessons, $group, missing: 'stage');
        $chosen = LessonTab::tryFrom($tab) ?? throw InvalidInput::field('tab', 'validation.invalidChoice');
        $listing = $this->lessons->listed($stage->id(), $chosen, $query);
        $shown = new ArrayCollection($listing->shown);

        return new LessonList(
            stage: LessonStageView::of($stage),
            today: $listing->today->toString(),
            tabs: $listing->tabs,
            servants: $this->preparersIn($group),
            items: LessonRows::of(
                $listing,
                $this->meetingsOf($shown),
                AccountReferences::of($this->accounts->namedByIds(LessonRows::accountIdsOf($shown, $listing->helpers))),
            ),
        );
    }

    /**
     * @return list<AccountReference>
     */
    private function preparersIn(Group $group): array
    {
        return AccountReference::listOf($this->accounts->activeNamedByIds($this->holders->thatCanIn(
            Action::Update,
            Resource::Lessons,
            $group,
        )));
    }

    /**
     * @param ReadableCollection<int, Lesson> $lessons
     *
     * @return list<NoMeeting|OffScheduleMeeting|PlannedMeeting|SessionMeeting>
     */
    private function meetingsOf(ReadableCollection $lessons): array
    {
        $schedules = $this->meetings->listSchedules(MeetingViews::scheduleIdsOf($lessons));

        return MeetingViews::of(
            $schedules,
            $this->meetings->listSessionsOn($schedules, MeetingViews::datesOf($lessons)),
            $lessons,
            ArchivedStructure::of($this->structure->archivedIds()),
        );
    }
}
