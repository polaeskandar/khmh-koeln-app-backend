<?php

declare(strict_types=1);

namespace App\Context\People\Domain\Repository;

use App\Context\People\Domain\ValueObject\ForgottenPerson;
use Symfony\Component\Uid\Uuid;

interface PersonTraceRepositoryInterface
{
    public function forget(Uuid $personId): ForgottenPerson;

    public function guardianLinksOf(Uuid $accountId): int;
}
