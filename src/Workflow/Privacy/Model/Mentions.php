<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Attendance\Application\Service\AttendanceErasures;
use App\Context\Learning\Application\Service\LearningErasures;
use App\Context\Meetings\Application\Service\MeetingErasures;
use App\Context\Meetings\Domain\ValueObject\CoveredSession;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\ValueObject\ErasablePerson;
use App\Library\Collections\Model\Collections;
use App\Library\Strings\Model\WholeWord;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class Mentions
{
    /**
     * @return list<Mention>
     */
    public static function of(
        ErasablePerson $person,
        MeetingErasures $meetings,
        AttendanceErasures $attendance,
        LearningErasures $learning,
        StructureLookup $structure,
    ): array {
        $notes = $meetings->notesCovering(RosterPlaces::of($person));
        $sessionIds = new ArrayCollection($notes->sessions)->map(static fn (CoveredSession $s): Uuid => $s->id);
        $stageIds = Collections::mapPresent($person->memberships, static fn (Membership $m): ?Uuid => $m->stageId());

        $found = [
            ...MentionRows::ofMeetings($notes),
            ...MentionRows::ofRemovals($attendance->removalNotesIn($sessionIds->getValues(), $person->id), $notes),
            ...MentionRows::ofLessons(
                $learning->lessonNotesOf($stageIds->getValues()),
                $structure->stages($stageIds->getValues()),
            ),
        ];

        return new ArrayCollection($found)
            ->filter(static fn (Mention $mention): bool => self::names($mention, $person->names))
            ->getValues();
    }

    /**
     * @param list<Mention> $mentions
     */
    public static function cleared(
        array $mentions,
        MeetingErasures $meetings,
        AttendanceErasures $attendance,
        LearningErasures $learning,
    ): int {
        foreach ($mentions as $mention) {
            match ($mention->where) {
                'removalReason' => $attendance->clearRemovalNote($mention->id),
                'lessonNote' => $learning->clearLessonNote($mention->id),
                default => $meetings->clearNote($mention->where, $mention->id),
            };
        }

        return new ArrayCollection($mentions)->count();
    }

    /**
     * @param list<string> $names
     */
    private static function names(Mention $mention, array $names): bool
    {
        return !new ArrayCollection($names)
            ->filter(static fn (string $name): bool => WholeWord::in($mention->text, $name))
            ->isEmpty();
    }
}
