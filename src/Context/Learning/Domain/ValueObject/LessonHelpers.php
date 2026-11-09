<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Context\Learning\Domain\Entity\Lesson;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class LessonHelpers
{
    /**
     * @param array<string, list<string>> $byLesson account ids by lesson id
     */
    public function __construct(
        private array $byLesson = [],
    ) {
    }

    /**
     * @return list<string>
     */
    public function idsOf(Lesson $lesson): array
    {
        return $this->byLesson[$lesson->id()->toRfc4122()] ?? [];
    }

    /**
     * @return list<Uuid>
     */
    public function accountIdsOf(Lesson $lesson): array
    {
        return new ArrayCollection($this->idsOf($lesson))->map(Uuid::fromString(...))->getValues();
    }

    /**
     * @return list<Uuid>
     */
    public function accountIds(): array
    {
        $ids = [];

        foreach ($this->byLesson as $accountIds) {
            foreach ($accountIds as $accountId) {
                $ids[] = Uuid::fromString($accountId);
            }
        }

        return $ids;
    }
}
