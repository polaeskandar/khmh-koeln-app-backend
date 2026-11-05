<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

use App\Context\Meetings\Domain\ValueObject\StructureImpact;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class StageList
{
    /**
     * @param list<StageEntry> $active
     * @param list<ArchivedStageEntry> $archived
     * @param ReadableCollection<string, ArchiveImpact> $impacts
     */
    public function __construct(
        public ServiceReference $service,
        public array $active,
        public array $archived,
        public ReadableCollection $impacts,
    ) {
    }

    /**
     * @param ReadableCollection<int, Stage> $stages
     * @param ReadableCollection<string, int> $members
     * @param ReadableCollection<string, StructureImpact> $impacts
     */
    public static function of(
        Service $service,
        ReadableCollection $stages,
        ReadableCollection $members,
        ReadableCollection $impacts,
    ): self {
        return new self(
            ServiceReference::of($service),
            $stages
                ->filter(static fn (Stage $stage): bool => !$stage->isArchived())
                ->map(static fn (Stage $stage): StageEntry => StageEntry::of(
                    $stage,
                    $members->get($stage->id()->toRfc4122()) ?? 0,
                ))
                ->getValues(),
            $stages
                ->filter(static fn (Stage $stage): bool => $stage->isArchived())
                ->map(ArchivedStageEntry::of(...))
                ->getValues(),
            $impacts->map(ArchiveImpact::of(...)),
        );
    }
}
