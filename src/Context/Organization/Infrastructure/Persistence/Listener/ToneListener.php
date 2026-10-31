<?php

declare(strict_types=1);

namespace App\Context\Organization\Infrastructure\Persistence\Listener;

use App\Context\Organization\Domain\Entity\ChurchService;
use App\Context\Organization\Domain\Repository\ChurchServiceRepositoryInterface;
use App\Context\Organization\Domain\ValueObject\Tone;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::prePersist, method: 'giveLeastUsedTone', entity: ChurchService::class)]
final readonly class ToneListener
{
    public function __construct(
        private ChurchServiceRepositoryInterface $services,
    ) {
    }

    public function giveLeastUsedTone(ChurchService $service): void
    {
        $service->takeTone(Tone::leastUsed($this->services->tonesInUse()));
    }
}
