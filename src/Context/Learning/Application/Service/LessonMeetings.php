<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Repository\LessonRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\MeetingChoice;
use App\Context\Learning\Domain\ValueObject\MeetingLink;
use App\Context\Learning\Domain\ValueObject\OfferedMeeting;
use Symfony\Component\Uid\Uuid;

final readonly class LessonMeetings
{
    public function __construct(
        private LessonRepositoryInterface $lessons,
    ) {
    }

    public function link(
        Uuid $stageId,
        MeetingChoice $choice,
        ?OfferedMeeting $offered,
        ?Lesson $editing = null,
    ): MeetingLink {
        $link = match (true) {
            $choice->sessionId !== null => MeetingLinks::toSession($stageId, $offered, $editing?->link()),
            $choice->scheduleId !== null => MeetingLinks::toSchedule($stageId, $offered, $editing?->link()),
            $choice->date !== null => new MeetingLink(null, $choice->day()),
            default => throw MeetingChoice::refusal('lessons.chooseWhen'),
        };

        $this->ensureFree($link, $editing);

        return $link;
    }

    public function countForSchedule(Uuid $scheduleId): int
    {
        return $this->lessons->countForSchedule($scheduleId);
    }

    private function ensureFree(MeetingLink $link, ?Lesson $editing): void
    {
        $holder = $link->scheduleId === null ? null : $this->lessons->currentAt($link->scheduleId, $link->teachingDate);

        if ($holder === null || $holder === $editing) {
            return;
        }

        throw MeetingChoice::refusal('lessons.meetingHasLesson', ['title' => $holder->text()->title]);
    }
}
