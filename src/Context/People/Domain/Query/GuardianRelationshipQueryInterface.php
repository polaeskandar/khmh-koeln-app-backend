<?php

declare(strict_types=1);

namespace App\Context\People\Domain\Query;

use App\Context\People\Domain\Entity\GuardianRelationship;
use App\Context\People\Domain\Entity\Person;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface GuardianRelationshipQueryInterface
{
    /**
     * @return ReadableCollection<int, GuardianRelationship>
     */
    public function listForChild(Person $child): ReadableCollection;

    /**
     * @return ReadableCollection<int, GuardianRelationship>
     */
    public function listOfAccount(Uuid $accountId): ReadableCollection;
}
