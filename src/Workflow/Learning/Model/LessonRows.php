<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\ValueObject\HomeworkTally;
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
     * @param ReadableCollection<string, User> $responsibles
     */
    private function __construct(
        private MeetingViews $meetings,
        private ReadableCollection $responsibles,
        private HomeworkTally $homework,
    ) {
    }

    /**
     * @return list<LessonRow>
     */
    public static function lookedUpIn(MeetingLookup $meetings, AccountLookup $accounts, LessonListing $listing): array
    {
        $rows = self::of($meetings, $accounts, $listing->shown, $listing->homework);

        return new ArrayCollection($listing->shown)->map($rows->rowOf(...))->getValues();
    }

    /**
     * @param list<Lesson> $lessons
     */
    public static function of(
        MeetingLookup $meetings,
        AccountLookup $accounts,
        array $lessons,
        HomeworkTally $homework,
    ): self {
        $shown = new ArrayCollection($lessons);
        $responsibleIds = Collections::mapPresent($shown, self::responsibleIdOf(...))->getValues();

        return new self(
            MeetingViews::of($meetings, $shown),
            Collections::firstPerKey($accounts->accounts($responsibleIds), self::userKey(...)),
            $homework,
        );
    }

    public function rowOf(Lesson $lesson): LessonRow
    {
        return new LessonRow(
            LessonSummary::of($lesson),
            $this->responsibleOf($lesson),
            $this->meetings->viewOf($lesson),
            LessonHomework::of($this->homework->of($lesson)),
        );
    }

    private function responsibleOf(Lesson $lesson): ?AccountReference
    {
        $responsible = $this->responsibles->get($lesson->responsibleId()?->toRfc4122() ?? '');

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
