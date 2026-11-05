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

    public function editable(string $id, ?int $version): Lesson
    {
        $lesson = $this->lessons->find(Uuid::fromString($id)) ?? throw NotFound::of('lesson');
        $lesson->ensureEditable();
        $this->versions->ensureCurrent($lesson, $version, 'lessons.changedMeanwhile');

        return $lesson;
    }

    public function create(LessonStage $stage, LessonDraft $draft, ?OfferedMeeting $offered, TeamChoice $team): Lesson
    {
        $text = $draft->text->merged(stored: null);
        $link = $this->meetings->link($stage->id, $draft->meeting ?? new MeetingChoice(), $offered);
        $chosen = $this->teams->chosen($team, lesson: null);

        return $this->saved(new Lesson($stage->id, $link, $text, $chosen->responsible), $draft, $chosen);
    }

    public function edit(
        Lesson $lesson,
        LessonStage $stage,
        LessonDraft $draft,
        ?OfferedMeeting $offered,
        TeamChoice $team,
    ): Lesson {
        $text = $draft->text->merged($lesson->text());
        $link = $this->relinked($lesson, $stage, $draft->meeting, $offered);
        $chosen = $this->teams->chosen($team, $lesson);
        $lesson->edit($text, $link, $chosen->responsible);

        return $this->saved($lesson, $draft, $chosen);
    }

    public function changeStatus(Lesson $lesson, string $status): Lesson
    {
        $chosen = LessonStatus::tryFrom($status) ?? throw InvalidInput::field('status', 'validation.invalidChoice');

        if ($lesson->changeStatus($chosen, $this->calendar->today(), $this->calendar->now())) {
            $this->lessons->save($lesson);
        }

        return $lesson;
    }

    private function relinked(
        Lesson $lesson,
        LessonStage $stage,
        ?MeetingChoice $choice,
        ?OfferedMeeting $offered,
    ): MeetingLink {
        if ($choice === null) {
            return $lesson->link();
        }

        $link = $this->meetings->link($stage->id, $choice, $offered, editing: $lesson);
        $lesson->ensureMovableTo($link, $this->calendar->today());

        return $link;
    }

    private function saved(Lesson $lesson, LessonDraft $draft, LessonTeam $team): Lesson
    {
        $lesson->describe($draft->plan->merged($lesson->plan()), $draft->plan->materials($lesson->materials()));
        $status = LessonStatus::from($draft->status ?? $lesson->state()->status->value);
        $lesson->changeStatus($status, $this->calendar->today(), $this->calendar->now());

        return $this->teams->savedWith($lesson, $team);
    }
}
