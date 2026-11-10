<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Repository\LessonRepositoryInterface;
use App\Context\Learning\Domain\Validation\MeetingLessonRule;
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

    public function chosenLink(
        Uuid $stageId,
        MeetingChoice $choice,
        ?OfferedMeeting $offered,
        ?Lesson $editing = null,
    ): MeetingLink {
        $link = match (true) {
            $choice->sessionId !== null, $choice->scheduleId !== null => self::offeredLink(
                $stageId,
                $offered,
                $editing?->link(),
            ),
            $choice->date !== null => new MeetingLink(null, $choice->day()),
            default => throw MeetingChoice::refusal('lessons.chooseWhen'),
        };

        $this->ensureFree($link, $editing);

        return $link;
    }

    private function ensureFree(MeetingLink $link, ?Lesson $editing): void
    {
        $holder = $link->scheduleId === null ? null : $this->lessons->currentAt($link->scheduleId, $link->teachingDate);
        MeetingLessonRule::ensureFreeToChoose($holder, $editing);
    }

    private static function offeredLink(Uuid $stageId, ?OfferedMeeting $offered, ?MeetingLink $current): MeetingLink
    {
        if ($offered?->belongsTo($stageId) !== true || $offered->isNewlyCanceled($current)) {
            throw MeetingChoice::refusal('lessons.chooseMeeting');
        }

        if ($offered->isNewlyUnscheduled($current)) {
            throw MeetingChoice::refusal('lessons.noMeetingThatDay', ['name' => $offered->name]);
        }

        return $offered->link;
    }
}
