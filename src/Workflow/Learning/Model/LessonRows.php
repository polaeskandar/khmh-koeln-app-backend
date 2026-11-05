<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\ValueObject\LessonListing;
use App\Context\Meetings\Application\Service\MeetingLookup;
use App\Library\Collections\Model\Collections;
use App\Workflow\Identity\Model\AccountReference;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class LessonRows
{
    /**
     * @return list<LessonRow>
     */
    public static function lookedUpIn(MeetingLookup $meetings, AccountLookup $accounts, LessonListing $listing): array
    {
        $shown = new ArrayCollection($listing->shown);
        $views = MeetingViews::of($meetings, $shown);
        $responsibleIds = Collections::mapPresent($shown, self::responsibleIdOf(...))->getValues();
        $responsibles = Collections::firstPerKey($accounts->accounts($responsibleIds), self::userKey(...));

        $rowOf = static fn (Lesson $lesson): LessonRow => new LessonRow(
            LessonSummary::of($lesson),
            self::responsibleOf($lesson, $responsibles),
            $views->viewOf($lesson),
            LessonHomework::of($listing->homeworkOf($lesson)),
        );

        return $shown->map($rowOf)->getValues();
    }

    /**
     * @param ReadableCollection<string, User> $responsibles
     */
    private static function responsibleOf(Lesson $lesson, ReadableCollection $responsibles): ?AccountReference
    {
        $responsible = $responsibles->get($lesson->responsibleId()?->toRfc4122() ?? '');

        return $responsible === null ? null : AccountReference::of($responsible);
    }

    private static function responsibleIdOf(Lesson $lesson): ?Uuid
    {
        return $lesson->responsibleId();
    }

    private static function userKey(User $user): string
    {
        return $user->id()->toRfc4122();
    }
}
