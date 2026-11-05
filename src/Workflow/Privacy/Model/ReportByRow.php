<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Learning\Domain\Entity\HomeworkCompletion;
use App\Context\People\Application\Service\PeopleExports;
use App\Context\People\Domain\Entity\Person;
use App\Library\Collections\Model\Collections;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ReportByRow
{
    public function __construct(
        public string $homework,
        public ?string $child,
        public ?string $completedAt,
        public ?string $undoneAt,
    ) {
    }

    /**
     * @param ReadableCollection<int, HomeworkCompletion> $reports
     *
     * @return list<self>
     */
    public static function listOf(ReadableCollection $reports, PeopleExports $people, Calendar $calendar): array
    {
        $ids = $reports->map(static fn (HomeworkCompletion $report): Uuid => $report->personId())->getValues();
        $children = Collections::firstPerKey($people->people($ids), keyOf: self::childKey(...));

        return $reports->map(
            static fn (HomeworkCompletion $report): self => new self(
                $report->assignment()->text()->title,
                $children->get($report->personId()->toRfc4122())?->name()->firstName,
                ExportInstant::of($report->completedAt(), $calendar),
                ExportInstant::of($report->undoneAt(), $calendar),
            ),
        )->getValues();
    }

    private static function childKey(Person $child): string
    {
        return $child->id()->toRfc4122();
    }
}
