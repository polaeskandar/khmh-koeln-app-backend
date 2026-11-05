<?php

declare(strict_types=1);

namespace App\Context\People\Domain\ValueObject;

final readonly class PersonErased
{
    public function __construct(
        public ForgottenPerson $people,
        public ErasedElsewhere $elsewhere,
    ) {
    }
}
