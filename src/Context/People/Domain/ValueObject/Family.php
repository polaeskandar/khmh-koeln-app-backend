<?php

declare(strict_types=1);

namespace App\Context\People\Domain\ValueObject;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Library\Collections\Model\Collections;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class Family
{
    /**
     * @param list<Person> $children
     * @param ReadableCollection<int, Membership> $currentMemberships
     */
    public function __construct(
        public array $children,
        private ReadableCollection $currentMemberships,
    ) {
    }

    /**
     * @return list<Uuid>
     */
    public function stageIdsOf(Person $child): array
    {
        return Collections::mapPresent(
            $this->currentMemberships->filter(
                static fn (Membership $membership): bool => $membership->person() === $child,
            ),
            static fn (Membership $membership): ?Uuid => $membership->stageId(),
        )->getValues();
    }
}
