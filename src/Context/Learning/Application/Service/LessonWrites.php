<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Enum\LessonStatus;
use App\Context\Learning\Domain\Repository\LessonRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\LessonDraft;
use App\Context\Learning\Domain\ValueObject\LessonStage;
use App\Context\Learning\Domain\ValueObject\MeetingChoice;
use App\Context\Learning\Domain\ValueObject\MeetingLink;
use App\Context\Learning\Domain\ValueObject\OfferedMeeting;
use App\Context\Learning\Domain\ValueObject\ResponsibleChoice;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Client\Calendar;
use Symfony\Component\Uid\Uuid;

final readonly class LessonWrites
{
    public function __construct(
        private LessonRepositoryInterface $lessons,
        private LessonMeetings $meetings,
        private Calendar $calendar,
    ) {
    }

    public function editable(string $id): Lesson
    {
        $lesson = $this->lessons->find(Uuid::fromString($id)) ?? throw NotFound::of('lesson');
        $lesson->ensureEditable();

        return $lesson;
    }

    public function create(
        LessonStage $stage,
        LessonDraft $draft,
        ?OfferedMeeting $offered,
        ResponsibleChoice $responsible,
    ): Lesson {
        $text = $draft->text->merged(stored: null);
        $link = $this->meetings->link($stage->id, $draft->meeting ?? new MeetingChoice(), $offered);
        $lesson = new Lesson($stage->id, $link, $text, $responsible->chosen(current: null));

        return $this->described($lesson, $draft);
    }

    public function edit(
        Lesson $lesson,
        LessonStage $stage,
        LessonDraft $draft,
        ?OfferedMeeting $offered,
        ResponsibleChoice $responsible,
    ): Lesson {
        $text = $draft->text->merged($lesson->text());
        $link = $this->relinked($lesson, $stage, $draft->meeting, $offered);
        $lesson->edit($text, $link, $responsible->chosen($lesson->responsibleId()));

        return $this->described($lesson, $draft);
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

    private function described(Lesson $lesson, LessonDraft $draft): Lesson
    {
        $lesson->describe($draft->plan->merged($lesson->plan()), $draft->plan->materials($lesson->materials()));
        $lesson->prepare(LessonStatus::from($draft->status ?? $lesson->state()->status->value));
        $this->lessons->save($lesson);

        return $lesson;
    }
}
