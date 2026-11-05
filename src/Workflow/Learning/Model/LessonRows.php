<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Learning\Application\Service\StageLessons;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\ValueObject\HomeworkTally;
use App\Context\Learning\Domain\ValueObject\LessonListing;
use App\Context\Meetings\Application\Service\MeetingLookup;
use App\Library\Collections\Model\Collections;
use App\Library\Strings\Model\InputText;
use App\Workflow\Identity\Model\AccountReference;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class LessonRows
{
    /**
     * @param ReadableCollection<string, User> $accounts
     * @param array<string, list<string>> $helpers
     */
    private function __construct(
        private MeetingViews $meetings,
        private ReadableCollection $accounts,
        private array $helpers,
        private HomeworkTally $homework,
    ) {
    }

    /**
     * @return list<LessonRow>
     */
    public static function lookedUpIn(
        MeetingLookup $meetings,
        AccountLookup $accounts,
        StageLessons $lessons,
        LessonListing $listing,
    ): array {
        $rows = self::of($meetings, $accounts, $lessons, $listing->shown, $listing->homework);

        return new ArrayCollection($listing->shown)->map($rows->rowOf(...))->getValues();
    }

    /**
     * @param list<Lesson> $lessons
     */
    public static function of(
        MeetingLookup $meetings,
        AccountLookup $accounts,
        StageLessons $stageLessons,
        array $lessons,
        HomeworkTally $homework,
    ): self {
        $shown = new ArrayCollection($lessons);
        $helpers = $stageLessons->helpersOf($lessons);
        $responsibleIds = Collections::mapPresent($shown, self::responsibleIdOf(...))->getValues();

        $helperIds = new ArrayCollection($helpers)->reduce(static fn (array $all, array $ids): array => [
            ...$all,
            ...$ids,
        ], initial: []);

        return new self(
            MeetingViews::of($meetings, $shown),
            Collections::firstPerKey(
                $accounts->accounts([
                    ...$responsibleIds,
                    ...new ArrayCollection($helperIds)->map(Uuid::fromString(...))->getValues(),
                ]),
                self::userKey(...),
            ),
            $helpers,
            $homework,
        );
    }

    public function rowOf(Lesson $lesson): LessonRow
    {
        return new LessonRow(
            LessonSummary::of($lesson),
            $this->responsibleOf($lesson),
            $this->helpersOf($lesson),
            $this->meetings->viewOf($lesson),
            LessonHomework::of($this->homework->of($lesson)),
        );
    }

    /**
     * @return list<AccountReference>
     */
    private function helpersOf(Lesson $lesson): array
    {
        $helpers = Collections::mapPresent(
            new ArrayCollection($this->helpers[$lesson->id()->toRfc4122()] ?? []),
            $this->accounts->get(...),
        );

        return new ArrayCollection(Collections::sortedBy($helpers, keyOf: self::nameKey(...)))->map(
            AccountReference::of(...),
        )->getValues();
    }

    private function responsibleOf(Lesson $lesson): ?AccountReference
    {
        $responsible = $this->accounts->get($lesson->responsibleId()?->toRfc4122() ?? '');

        return $responsible === null ? null : AccountReference::of($responsible);
    }

    private static function responsibleIdOf(Lesson $lesson): ?Uuid
    {
        return $lesson->responsibleId();
    }

    private static function nameKey(User $user): string
    {
        $name = InputText::of($user->profile()->displayName->value)->lowered()->toString();

        return $name . ' ' . $user->id()->toRfc4122();
    }

    private static function userKey(User $user): string
    {
        return $user->id()->toRfc4122();
    }
}
