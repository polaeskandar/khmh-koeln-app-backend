<?php

declare(strict_types=1);

namespace App\Context\People\Domain\ValueObject;

use App\Context\People\Domain\Entity\Membership;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ErasablePerson
{
    /**
     * @param ReadableCollection<int, Membership> $memberships
     */
    public function __construct(
        public Uuid $id,
        public ReadableCollection $memberships,
    ) {
    }
}
