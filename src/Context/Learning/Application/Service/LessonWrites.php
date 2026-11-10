<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Enum\LessonStatus;
use App\Context\Learning\Domain\Repository\LessonRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\LessonDraft;
use App\Context\Learning\Domain\ValueObject\LessonStage;
use App\Context\Learning\Domain\ValueObject\LessonTeam;
use App\Context\Learning\Domain\ValueObject\MeetingChoice;
use App\Context\Learning\Domain\ValueObject\MeetingLink;
use App\Context\Learning\Domain\ValueObject\OfferedMeeting;
use App\Context\Learning\Domain\ValueObject\TeamChoice;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Client\Calendar;
use App\Platform\Doctrine\Contract\VersionChecks;
use Symfony\Component\Uid\Uuid;

final readonly class LessonWrites
{
    public function __construct(
        private LessonRepositoryInterface $lessons,
        private LessonMeetings $meetings,
        private LessonTeams $teams,
        private VersionChecks $versions,
        private Calendar $calendar,
    ) {
    }

    /**
     * @param callable(Lesson): void $ensureAllowed
     */
    public function editable(Uuid $id, ?int $version, callable $ensureAllowed): Lesson
    {
        $lesson = $this->lessons->find($id) ?? throw NotFound::of('lesson');

        $ensureAllowed($lesson);
        $lesson->state()->ensureEditable();
        $this->versions->ensureCurrent($lesson, $version, 'lessons.changedMeanwhile');

        return $lesson;
    }

    public function create(LessonStage $stage, LessonDraft $draft, ?OfferedMeeting $offered, TeamChoice $team): Lesson
    {
        $link = fn (): MeetingLink => $this->meetings->link($stage->id, $draft->meeting, $offered);
        $chosen = fn (): LessonTeam => $this->teams->chosen($team, lesson: null);
        $this->check($draft, $link, $chosen);
        $lessonTeam = $chosen();

        $lesson = new Lesson($stage->id, $link(), $draft->text->text(), $lessonTeam->responsible);

        return $this->described($lesson, $draft, $lessonTeam, unchanged: LessonStatus::Draft);
    }

    public function edit(
        Lesson $lesson,
        LessonStage $stage,
        LessonDraft $draft,
        ?OfferedMeeting $offered,
        TeamChoice $team,
    ): Lesson {
        $link = fn (): MeetingLink => $this->movable($lesson, $this->meetings->link(
            $stage->id,
            $draft->meeting,
            $offered,
            editing: $lesson,
        ));

        $chosen = fn (): LessonTeam => $this->teams->chosen($team, $lesson);
        $this->check($draft, $link, $chosen);
        $lessonTeam = $chosen();
        $lesson->edit($draft->text->text(), $link(), $lessonTeam->responsible);

        return $this->described($lesson, $draft, $lessonTeam, unchanged: $lesson->state()->status);
    }

    /**
     * @param callable(): MeetingLink $link
     * @param callable(): LessonTeam $team
     */
    private function check(LessonDraft $draft, callable $link, callable $team): void
    {
        InvalidInput::all($draft->text->text(...), $draft->plan->plan(...), $draft->plan->materials(...), $link, $team);
    }

    private function movable(Lesson $lesson, MeetingLink $link): MeetingLink
    {
        return $lesson->state()->isCompletedAfter($link->teachingDate, $this->calendar->today())
            ? throw MeetingChoice::refusal('lessons.taughtAfterDate')
            : $link;
    }

    private function described(Lesson $lesson, LessonDraft $draft, LessonTeam $team, LessonStatus $unchanged): Lesson
    {
        $lesson->describe($draft->plan->plan(), $draft->plan->materials());
        $status = $draft->status === null ? $unchanged : LessonStatus::from($draft->status);
        $lesson->changeState($lesson->state()->withStatus($status, $this->calendar->now()));

        return $this->teams->savedWith($lesson, $team);
    }
}
