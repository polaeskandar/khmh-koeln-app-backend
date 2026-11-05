<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\ServiceStages;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Application\Service\Rosters;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

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
        public bool $canManageStructure,
    ) {
    }

    public static function current(Service $service, ServiceStages $stages, Rosters $rosters, AccessCheck $access): self
    {
        $held = $stages->ofService($service);

        return self::of(
            $service,
            $held,
            $rosters->stageMembersToday($held->map(static fn (Stage $stage): Uuid => $stage->id())->getValues()),
            canManageStructure: !$service->isArchived() && $access->allows(Action::Update, Resource::Stages),
        );
    }

    /**
     * @param ReadableCollection<int, Stage> $stages
     * @param ReadableCollection<string, int> $members
     */
    public static function of(
        Service $service,
        ReadableCollection $stages,
        ReadableCollection $members,
        bool $canManageStructure,
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
            $canManageStructure,
        );
    }
}
