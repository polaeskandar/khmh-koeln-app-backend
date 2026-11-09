<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\GuardianLink;
use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Repository\GuardianLinkRepositoryInterface;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class PeopleExports
{
    public function __construct(
        private PersonRepositoryInterface $people,
        private GuardianLinkRepositoryInterface $links,
    ) {
    }

    public function person(string $id): ?Person
    {
        return $this->people->find(Uuid::fromString($id));
    }

    /**
     * @return ReadableCollection<int, Membership>
     */
    public function membershipsOf(Person $person): ReadableCollection
    {
        return $this->people->membershipsOf(new ArrayCollection([$person]));
    }

    /**
     * @return ReadableCollection<int, GuardianLink>
     */
    public function linksOfChild(Person $child): ReadableCollection
    {
        return $this->links->allForChild($child);
    }

    /**
     * @return ReadableCollection<int, GuardianLink>
     */
    public function linksOfAccount(Uuid $accountId): ReadableCollection
    {
        return $this->links->allOfAccount($accountId);
    }

    /**
     * @param list<Uuid> $ids
     *
     * @return ReadableCollection<int, Person>
     */
    public function people(array $ids): ReadableCollection
    {
        return $this->people->findByIds($ids);
    }
}
