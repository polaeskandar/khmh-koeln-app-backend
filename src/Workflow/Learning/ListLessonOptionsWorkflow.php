<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\LessonOptionLookup;
use App\Context\Meetings\Application\Service\MeetingLookup;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Meetings\Domain\ValueObject\PlannedDate;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Library\Problem\Model\Ids;
use App\Workflow\Learning\Model\LessonOptions;
use App\Workflow\Organization\Model\StructureGroup;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ListLessonOptionsWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StructureLookup $structure,
        private MeetingLookup $meetings,
        private LessonOptionLookup $lessons,
        private AccountLookup $accounts,
    ) {
    }

    public function __invoke(string $stageId, ?string $lessonId): LessonOptions
    {
        $this->access->require(Action::Update, Resource::Lessons, refusal: 'lessons.prepareStageOnly');

        $stage = $this->structure->stage(Ids::toUuid($stageId, notFound: 'stage'));
        $group = StructureGroup::ofStage($stage);
        $this->access->requireIn(Action::Update, Resource::Lessons, $group, missing: 'stage');

        $edited = $this->lessons->edited(
            $stage->id(),
            $lessonId === null ? null : Ids::toUuid($lessonId, notFound: 'lesson'),
        );

        $window = $this->lessons->windowFor($edited);
        $archived = ArchivedStructure::of($this->structure->archivedIds());
        $dates = $this->meetings->datesOfStage($stage->id(), $archived, $window->from, $window->to);

        return LessonOptions::of(
            $window,
            $dates,
            $this->lessons->takenIn(self::scheduleIdsOf($dates), $window, $edited),
            $this->accounts->activeAllowedIn(Action::Update, Resource::Lessons, $group),
        );
    }

    /**
     * @param list<PlannedDate> $dates
     *
     * @return list<Uuid>
     */
    private static function scheduleIdsOf(array $dates): array
    {
        return new ArrayCollection($dates)->map(
            static fn (PlannedDate $date): Uuid => $date->schedule->id(),
        )->getValues();
    }
}
