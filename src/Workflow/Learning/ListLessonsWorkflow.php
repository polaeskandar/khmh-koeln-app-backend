<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\StageLessons;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Meetings\Application\Service\MeetingLookup;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Workflow\Identity\Model\AccountReference;
use App\Workflow\Identity\Model\AccountReferences;
use App\Workflow\Learning\Model\LessonList;
use App\Workflow\Learning\Model\LessonRows;
use App\Workflow\Learning\Model\LessonStageView;
use App\Workflow\Learning\Model\MeetingView;
use App\Workflow\Learning\Model\MeetingViews;
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
    ) {
    }

    public function __invoke(string $stageId, string $tab, ?string $query): LessonList
    {
        $this->access->require(Action::Read, Resource::Lessons);

        $stage = $this->structure->stage($stageId);
        $listing = $this->lessons->listed($stage->id(), $tab, $query);
        $shown = new ArrayCollection($listing->shown);
        $preparers = $this->accounts->activeAllowedTo(Action::Update, Resource::Lessons);

        return new LessonList(
            stage: LessonStageView::of($stage),
            today: $listing->today->toString(),
            tabs: $listing->tabs,
            servants: new ArrayCollection($preparers)->map(AccountReference::of(...))->getValues(),
            items: LessonRows::of(
                $listing,
                $this->meetingsOf($shown),
                AccountReferences::of($this->accounts->accounts(LessonRows::responsibleIdsOf($shown))),
            ),
        );
    }

    /**
     * @param ReadableCollection<int, Lesson> $lessons
     *
     * @return list<MeetingView>
     */
    private function meetingsOf(ReadableCollection $lessons): array
    {
        $schedules = $this->meetings->schedules(MeetingViews::scheduleIdsOf($lessons));

        return MeetingViews::of(
            $schedules,
            $this->meetings->sessionsOn($schedules, MeetingViews::datesOf($lessons)),
            $lessons,
            ArchivedStructure::of($this->structure->archivedIds()),
        );
    }
}
