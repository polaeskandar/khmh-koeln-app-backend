<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Attendance\Domain\ValueObject\RemovalNote;
use App\Context\Learning\Domain\ValueObject\LessonNote;
use App\Context\Meetings\Domain\ValueObject\CoveredSession;
use App\Context\Meetings\Domain\ValueObject\MeetingNote;
use App\Context\Meetings\Domain\ValueObject\MeetingNotes;
use App\Context\Organization\Domain\Entity\Stage;
use App\Library\Collections\Model\Collections;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class MentionRows
{
    /**
     * @return list<Mention>
     */
    public static function ofMeetings(MeetingNotes $notes): array
    {
        return new ArrayCollection($notes->notes)->map(
            static fn (MeetingNote $note): Mention => new Mention(
                $note->id,
                $note->where,
                $note->text,
                $note->date,
                $note->meeting,
            ),
        )->getValues();
    }

    /**
     * @param list<RemovalNote> $removals
     *
     * @return list<Mention>
     */
    public static function ofRemovals(array $removals, MeetingNotes $notes): array
    {
        $sessions = Collections::firstPerKey(new ArrayCollection($notes->sessions), keyOf: self::sessionKey(...));

        return new ArrayCollection($removals)->map(static function (RemovalNote $note) use ($sessions): Mention {
            $session = $sessions->get($note->sessionId);

            return new Mention($note->id, 'removalReason', $note->text, $session?->date, $session?->meeting);
        })->getValues();
    }

    /**
     * @param list<LessonNote> $lessons
     * @param ReadableCollection<int, Stage> $stages
     *
     * @return list<Mention>
     */
    public static function ofLessons(array $lessons, ReadableCollection $stages): array
    {
        $named = Collections::firstPerKey($stages, keyOf: self::stageKey(...));

        return new ArrayCollection($lessons)->map(
            static fn (LessonNote $note): Mention => new Mention(
                $note->id,
                'lessonNote',
                $note->text,
                $note->date,
                $named->get($note->stageId)?->name()->value,
            ),
        )->getValues();
    }

    private static function sessionKey(CoveredSession $session): string
    {
        return $session->id->toRfc4122();
    }

    private static function stageKey(Stage $stage): string
    {
        return $stage->id()->toRfc4122();
    }
}
