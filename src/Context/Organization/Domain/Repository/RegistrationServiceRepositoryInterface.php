<?php

declare(strict_types=1);

namespace App\Context\Organization\Domain\Repository;

use App\Context\Organization\Domain\Entity\RegistrationService;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface RegistrationServiceRepositoryInterface
{
    public function add(RegistrationService $registrationService): void;

    /**
     * @param list<Uuid> $accountIds
     *
     * @return ReadableCollection<int, RegistrationService>
     */
    public function ofAccounts(array $accountIds): ReadableCollection;

    public function removeFor(Uuid $accountId): void;
}
