<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Learning\Domain\Entity\HomeworkCompletion;
use App\Context\People\Domain\Entity\Person;
use App\Library\Collections\Model\Collections;
use DateTimeImmutable;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ReportByRow
{
    public function __construct(
        public string $homework,
        public ?string $child,
        public DateTimeImmutable $completedAt,
        public ?DateTimeImmutable $undoneAt,
    ) {
    }

    /**
     * @param ReadableCollection<int, HomeworkCompletion> $reports
     *
     * @return list<Uuid>
     */
    public static function childIdsOf(ReadableCollection $reports): array
    {
        return $reports->map(static fn (HomeworkCompletion $report): Uuid => $report->personId())->getValues();
    }

    /**
     * @param ReadableCollection<int, HomeworkCompletion> $reports
     * @param ReadableCollection<int, Person> $people
     *
     * @return list<self>
     */
    public static function listOf(ReadableCollection $reports, ReadableCollection $people): array
    {
        $children = Collections::firstPerKey($people, keyOf: self::childKey(...));

        return $reports->map(
            static fn (HomeworkCompletion $report): self => new self(
                $report->assignment()->text()->title,
                $children->get($report->personId()->toRfc4122())?->name()->firstName,
                $report->completedAt(),
                $report->undoneAt(),
            ),
        )->getValues();
    }

    private static function childKey(Person $child): string
    {
        return $child->id()->toRfc4122();
    }
}
