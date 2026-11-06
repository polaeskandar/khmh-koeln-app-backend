<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class StageList
{
    /**
     * @param list<StageEntry> $active
     * @param list<ArchivedStageEntry> $archived
     */
    public function __construct(
        public ServiceReference $service,
        public array $active,
        public array $archived,
    ) {
    }

    /**
     * @param ReadableCollection<int, Stage> $stages
     */
    public static function of(Service $service, ReadableCollection $stages): self
    {
        return new self(
            ServiceReference::of($service),
            $stages
                ->filter(static fn (Stage $stage): bool => !$stage->isArchived())
                ->map(StageEntry::of(...))
                ->getValues(),
            $stages
                ->filter(static fn (Stage $stage): bool => $stage->isArchived())
                ->map(ArchivedStageEntry::of(...))
                ->getValues(),
        );
    }
}
