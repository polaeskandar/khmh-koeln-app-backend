<?php

declare(strict_types=1);

namespace App\Interface\Application\Identity\Service;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Repository\ServiceRepositoryInterface;
use App\Interface\Application\Identity\Input\RegistrationInput;
use App\Library\Problem\Exception\InvalidInput;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class ChosenServices
{
    public function __construct(
        private ServiceRepositoryInterface $services,
    ) {
    }

    /**
     * @return ReadableCollection<int, Service>
     */
    public function of(RegistrationInput $registration): ReadableCollection
    {
        if (!$registration->isServant()) {
            return new ArrayCollection();
        }

        $ids = new ArrayCollection($registration->serviceIds);

        $chosen = $this->services->active()->filter(static fn (Service $service): bool => $ids->contains(
            $service->id()->toRfc4122(),
        ));

        if ($chosen->isEmpty() || $chosen->count() !== $ids->count()) {
            throw InvalidInput::field('registration.serviceIds', 'accounts.chooseService');
        }

        return $chosen;
    }
}
