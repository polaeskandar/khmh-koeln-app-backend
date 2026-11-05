<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Entity\RegistrationService;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Enum\ServiceFilter;
use App\Context\Organization\Domain\Repository\RegistrationServiceRepositoryInterface;
use App\Context\Organization\Domain\Repository\ServiceRepositoryInterface;
use App\Library\Problem\Exception\InvalidInput;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class RegistrationServices
{
    public function __construct(
        private ServiceRepositoryInterface $services,
        private RegistrationServiceRepositoryInterface $registrationServices,
    ) {
    }

    /**
     * @return ReadableCollection<int, Service>
     */
    public function active(int $limit): ReadableCollection
    {
        return new ArrayCollection($this->services->listed(ServiceFilter::Active)->slice(offset: 0, length: $limit));
    }

    /**
     * @param list<string> $ids
     *
     * @return ReadableCollection<int, Service>
     */
    public function chosen(array $ids): ReadableCollection
    {
        $wanted = new ArrayCollection($ids);

        $chosen = $this->services
            ->listed(ServiceFilter::Active)
            ->filter(static fn (Service $service): bool => $wanted->contains($service->id()->toRfc4122()));

        if ($chosen->isEmpty() || $chosen->count() !== $wanted->count()) {
            throw InvalidInput::field('registration.serviceIds', 'accounts.chooseService');
        }

        return $chosen;
    }

    /**
     * @param ReadableCollection<int, Service> $services
     */
    public function record(Uuid $accountId, ReadableCollection $services): void
    {
        foreach ($services as $service) {
            $this->registrationServices->add(new RegistrationService($accountId, $service));
        }
    }
}
