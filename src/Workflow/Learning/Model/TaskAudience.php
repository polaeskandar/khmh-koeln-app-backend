<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\ValueObject\TaskReport;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\ValueObject\RosterBook;
use App\Library\Collections\Model\Collections;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class TaskAudience
{
    /**
     * @param list<Person> $members
     * @param ReadableCollection<string, TaskReport> $reports
     * @param list<Person> $leavers
     */
    private function __construct(
        public array $members,
        public ReadableCollection $reports,
        public array $leavers,
    ) {
    }

    /**
     * @param list<Person> $members
     * @param ReadableCollection<string, TaskReport> $reports
     */
    public static function of(array $members, ReadableCollection $reports, RosterBook $rosters): self
    {
        $memberIds = new ArrayCollection($members)->map(self::keyOf(...));

        $leavers = Collections::mapPresent(
            Collections::withoutKeys($reports, $memberIds->getValues()),
            static fn (TaskReport $report): ?Person => $rosters->personOf($report->personId),
        );

        return new self($members, $reports, $leavers->getValues());
    }

    public function reportOf(Person $person): ?TaskReport
    {
        return $this->reports->get(self::keyOf($person));
    }

    public function isMember(Person $person): bool
    {
        return new ArrayCollection($this->members)->contains($person);
    }

    private static function keyOf(Person $person): string
    {
        return $person->id()->toRfc4122();
    }
}
