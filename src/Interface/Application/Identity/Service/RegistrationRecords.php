<?php

declare(strict_types=1);

namespace App\Interface\Application\Identity\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Context\Organization\Domain\Entity\RegistrationService;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Repository\RegistrationServiceRepositoryInterface;
use App\Platform\Doctrine\Contract\Transactions;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class RegistrationRecords
{
    public function __construct(
        private UserRepositoryInterface $users,
        private RegistrationServiceRepositoryInterface $registrationServices,
        private Transactions $transactions,
    ) {
    }

    /**
     * @param ReadableCollection<int, Service> $services
     */
    public function storeUnlessTaken(User $user, ReadableCollection $services): void
    {
        if ($this->users->findByEmail($user->profile()->email->value) !== null) {
            return;
        }

        try {
            $this->transactions->run(fn () => $this->store($user, $services));
        } catch (UniqueConstraintViolationException) {
            return;
        }
    }

    /**
     * @param ReadableCollection<int, Service> $services
     */
    private function store(User $user, ReadableCollection $services): void
    {
        $this->users->save($user);

        foreach ($services as $service) {
            $this->registrationServices->add(new RegistrationService($user->id(), $service));
        }
    }
}
