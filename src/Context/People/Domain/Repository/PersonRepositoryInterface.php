<?php

declare(strict_types=1);

namespace App\Context\People\Domain\Repository;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use Doctrine\Common\Collections\ReadableCollection;

interface PersonRepositoryInterface
{
    public function save(Person $person, Membership ...$memberships): void;

    /**
     * @param list<string> $words
     *
     * @return ReadableCollection<int, Person>
     */
    public function searchActive(array $words, int $limit): ReadableCollection;

    /**
     * @param ReadableCollection<int, Person> $people
     *
     * @return ReadableCollection<int, Membership>
     */
    public function membershipsOf(ReadableCollection $people): ReadableCollection;
}
