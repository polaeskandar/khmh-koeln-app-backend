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
use App\Library\Problem\Exception\InvalidInput;
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
        $lesson = (Uuid::isValid($id) ? $this->lessons->find(Uuid::fromString($id)) : null) ?? throw NotFound::of(
            'lesson',
        );

        $lesson->state()->ensureEditable();

        return $lesson;
    }

    public function create(
        LessonStage $stage,
        LessonDraft $draft,
        ?OfferedMeeting $offered,
        ResponsibleChoice $responsible,
    ): Lesson {
        $link = fn (): MeetingLink => $this->meetings->link($stage->id, $draft->meeting, $offered);
        $responsibleId = static fn (): Uuid => $responsible->chosen(current: null);
        $this->check($draft, $link, $responsibleId);

        $lesson = new Lesson($stage->id, $link(), $draft->text->text(), $responsibleId());

        return $this->described($lesson, $draft, unchanged: LessonStatus::Draft);
    }

    public function edit(
        Lesson $lesson,
        LessonStage $stage,
        LessonDraft $draft,
        ?OfferedMeeting $offered,
        ResponsibleChoice $responsible,
    ): Lesson {
        $link = fn (): MeetingLink => $this->movable($lesson, $this->meetings->link(
            $stage->id,
            $draft->meeting,
            $offered,
            editing: $lesson,
        ));

        $responsibleId = static fn (): Uuid => $responsible->chosen($lesson->responsibleId());
        $this->check($draft, $link, $responsibleId);
        $lesson->edit($draft->text->text(), $link(), $responsibleId());

        return $this->described($lesson, $draft, unchanged: $lesson->state()->status);
    }

    /**
     * @param callable(): MeetingLink $link
     * @param callable(): Uuid $responsibleId
     */
    private function check(LessonDraft $draft, callable $link, callable $responsibleId): void
    {
        InvalidInput::all(
            $draft->text->text(...),
            $draft->plan->plan(...),
            $draft->plan->materials(...),
            $link,
            $responsibleId,
        );
    }

    private function movable(Lesson $lesson, MeetingLink $link): MeetingLink
    {
        $taught = $lesson->state()->status === LessonStatus::Completed;

        return $taught && $this->calendar->today()->isBefore($link->teachingDate)
            ? throw MeetingChoice::refusal('lessons.taughtAfterDate')
            : $link;
    }

    private function described(Lesson $lesson, LessonDraft $draft, LessonStatus $unchanged): Lesson
    {
        $lesson->describe($draft->plan->plan(), $draft->plan->materials());
        $lesson->prepare($draft->status === null ? $unchanged : LessonStatus::from($draft->status));
        $this->lessons->save($lesson);

        return $lesson;
    }
}
