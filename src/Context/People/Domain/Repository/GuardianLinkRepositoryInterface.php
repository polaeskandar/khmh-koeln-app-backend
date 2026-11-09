<?php

declare(strict_types=1);

namespace App\Context\People\Domain\Repository;

use App\Context\People\Domain\Entity\GuardianLink;
use App\Context\People\Domain\Entity\Person;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface GuardianLinkRepositoryInterface
{
    public function save(GuardianLink $link): void;

    public function find(Uuid $id): ?GuardianLink;

    /**
     * @param positive-int $limit
     *
     * @return ReadableCollection<int, GuardianLink>
     */
    public function approved(int $limit): ReadableCollection;

    public function approvedFor(Uuid $accountId, Person $child): ?GuardianLink;

    /**
     * @return ReadableCollection<int, GuardianLink>
     */
    public function approvedOf(Uuid $accountId): ReadableCollection;

    /**
     * @param list<Uuid> $childIds
     *
     * @return ReadableCollection<int, GuardianLink>
     */
    public function approvedForChildren(array $childIds): ReadableCollection;

    /**
     * @return ReadableCollection<int, GuardianLink>
     */
    public function allForChild(Person $child): ReadableCollection;

    /**
     * @return ReadableCollection<int, GuardianLink>
     */
    public function allOfAccount(Uuid $accountId): ReadableCollection;
}
