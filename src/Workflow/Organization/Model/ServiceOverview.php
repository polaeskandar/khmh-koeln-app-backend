<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class ServiceOverview
{
    /**
     * @param list<StageSummary> $stages
     */
    public function __construct(
        public ServiceSummary $service,
        public array $stages,
    ) {
    }

    /**
     * @param ReadableCollection<int, Stage> $stages
     */
    public static function of(Service $service, ReadableCollection $stages): self
    {
        return new self(
            new ServiceSummary(
                id: $service->id()->toRfc4122(),
                name: $service->name()->value,
                description: $service->description()->value,
                tone: $service->tone()->value,
                archivedAt: $service->archivedAt(),
            ),
            $stages->map(StageSummary::of(...))->getValues(),
        );
    }
}
