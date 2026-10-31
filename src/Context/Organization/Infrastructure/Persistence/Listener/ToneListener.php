<?php

declare(strict_types=1);

namespace App\Context\Organization\Infrastructure\Persistence\Listener;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Repository\ServiceRepositoryInterface;
use App\Context\Organization\Domain\ValueObject\Tone;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::prePersist, method: 'giveLeastUsedTone', entity: Service::class)]
final readonly class ToneListener
{
    public function __construct(
        private ServiceRepositoryInterface $services,
    ) {
    }

    public function giveLeastUsedTone(Service $service): void
    {
        $service->takeTone(Tone::leastUsed($this->services->tonesInUse()));
    }
}
