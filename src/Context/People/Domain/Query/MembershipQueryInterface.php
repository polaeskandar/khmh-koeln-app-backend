<?php

declare(strict_types=1);

namespace App\Context\People\Domain\Query;

use Symfony\Component\Uid\Uuid;

interface MembershipQueryInterface
{
    public function countInService(Uuid $serviceId): int;

    public function countInStage(Uuid $stageId): int;
}
