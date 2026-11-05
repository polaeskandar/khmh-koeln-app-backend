<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\ValueObject\LessonListing;
use App\Library\Collections\Model\Collections;
use App\Workflow\Identity\Model\AccountReferences;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use LogicException;
use Symfony\Component\Uid\Uuid;

final readonly class LessonRows
{
    /**
     * @param list<MeetingView> $meetings
     *
     * @return list<LessonRow>
     */
    public static function of(LessonListing $listing, array $meetings, AccountReferences $responsibles): array
    {
        $rowOf = static fn (Lesson $lesson, int $index): LessonRow => new LessonRow(
            LessonSummary::of($lesson),
            $responsibles->referenceOf($lesson->responsibleId()),
            $meetings[$index] ?? throw new LogicException('Every shown lesson needs its meeting view.'),
            LessonHomework::of($listing->homeworkOf($lesson)),
        );

        return new ArrayCollection(Collections::mapArray($listing->shown, $rowOf))->getValues();
    }

    /**
     * @param ReadableCollection<int, Lesson> $lessons
     *
     * @return list<Uuid>
     */
    public static function responsibleIdsOf(ReadableCollection $lessons): array
    {
        $responsibleOf = static fn (Lesson $lesson): ?Uuid => $lesson->responsibleId();

        return Collections::mapPresent($lessons, $responsibleOf)->getValues();
    }
}
