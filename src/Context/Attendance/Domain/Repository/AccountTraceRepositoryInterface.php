<?php

declare(strict_types=1);

namespace App\Context\Attendance\Domain\Repository;

use Symfony\Component\Uid\Uuid;

interface AccountTraceRepositoryInterface
{
    public function forget(Uuid $accountId): int;
}
