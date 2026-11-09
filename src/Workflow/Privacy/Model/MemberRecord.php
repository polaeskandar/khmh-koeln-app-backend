<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\PersonStatus;
use App\Library\Time\Model\LocalDate;
use App\Workflow\People\Model\PersonReference;

final readonly class MemberRecord
{
    public function __construct(
        public PersonReference $name,
        public PersonStatus $status,
        public string $recordedSince,
    ) {
    }

    public static function of(Person $person, LocalDate $recordedSince): self
    {
        return new self(PersonReference::of($person), $person->status(), $recordedSince->toString());
    }
}
