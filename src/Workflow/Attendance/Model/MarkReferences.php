<?php

declare(strict_types=1);

namespace App\Workflow\Attendance\Model;

use App\Context\Attendance\Domain\Entity\Attendance;
use App\Context\Attendance\Domain\ValueObject\SessionMarks;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Library\Time\Client\Calendar;
use App\Workflow\People\Model\PersonReference;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class MarkReferences
{
    private function __construct(
        private AccountNames $accounts,
        private Calendar $calendar,
    ) {
    }

    public static function lookedUpIn(AccountLookup $lookup, Calendar $calendar, SessionMarks $marks): self
    {
        $all = new ArrayCollection([...$marks->current->getValues(), ...$marks->removed->getValues()]);

        return new self(AccountNames::lookedUpIn($lookup, [
            ...$all->map(static fn (Attendance $mark): ?Uuid => $mark->recordedBy())->getValues(),
            ...$all->map(static fn (Attendance $mark): ?Uuid => $mark->removal()->by)->getValues(),
        ]), $calendar);
    }

    public function mark(Attendance $mark): MarkReference
    {
        return new MarkReference(
            $mark->id()->toRfc4122(),
            $this->calendar->formatInstant($mark->recordedAt()),
            $this->accounts->of($mark->recordedBy()),
        );
    }

    public function markOf(?Attendance $mark): ?MarkReference
    {
        return $mark === null ? null : $this->mark($mark);
    }

    public function correction(Attendance $mark, PersonReference $person): Correction
    {
        $removal = $mark->removal();

        return new Correction(
            $this->mark($mark),
            $person,
            $removal->at === null ? '' : $this->calendar->formatInstant($removal->at),
            $this->accounts->of($removal->by),
            $removal->reason,
        );
    }
}
