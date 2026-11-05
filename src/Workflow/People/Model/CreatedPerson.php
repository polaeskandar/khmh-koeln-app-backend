<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

use App\Context\People\Domain\ValueObject\Enrolment;

final readonly class CreatedPerson
{
    public function __construct(
        public PersonView $person,
        public ?MembershipView $membership,
    ) {
    }

    public static function of(Enrolment $enrolment): self
    {
        $membership = $enrolment->membership;

        return new self(
            PersonView::of($enrolment->person),
            $membership === null ? null : MembershipView::of($membership),
        );
    }
}
