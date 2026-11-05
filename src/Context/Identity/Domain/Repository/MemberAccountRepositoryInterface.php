<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Repository;

use App\Context\Identity\Domain\Entity\User;
use Symfony\Component\Uid\Uuid;

interface MemberAccountRepositoryInterface
{
    public function holderOf(Uuid $personId): ?User;
}
