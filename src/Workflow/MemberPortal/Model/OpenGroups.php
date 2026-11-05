<?php

declare(strict_types=1);

namespace App\Workflow\MemberPortal\Model;

use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Domain\Entity\Membership;
use App\Library\Collections\Model\Collections;
use Doctrine\Common\Collections\ReadableCollection;
use LogicException;
use Symfony\Component\Uid\Uuid;

final readonly class OpenGroups
{
    /**
     * @param ReadableCollection<non-empty-string, Service> $services
     * @param ReadableCollection<non-empty-string, Stage> $stages
     */
    private function __construct(
        private ReadableCollection $services,
        private ReadableCollection $stages,
    ) {
    }

    /**
     * @param ReadableCollection<int, Membership> $memberships
     */
    public static function lookedUpIn(StructureLookup $structure, ReadableCollection $memberships): self
    {
        $serviceIds = $memberships->map(static fn (Membership $held): Uuid => $held->serviceId());
        $stageIds = Collections::mapPresent($memberships, static fn (Membership $held): ?Uuid => $held->stageId());

        $services = $structure
            ->services($serviceIds->getValues())
            ->filter(static fn (Service $one): bool => !$one->isArchived());

        $stages = $structure
            ->stages($stageIds->getValues())
            ->filter(static fn (Stage $one): bool => !$one->isArchived());

        return new self(
            Collections::firstPerKey($services, keyOf: static fn (Service $one): string => $one->id()->toRfc4122()),
            Collections::firstPerKey($stages, keyOf: static fn (Stage $one): string => $one->id()->toRfc4122()),
        );
    }

    public function includes(Membership $membership): bool
    {
        $stageId = $membership->stageId();
        $stageOpen = $stageId === null || $this->stages->containsKey($stageId->toRfc4122());

        return $stageOpen && $this->services->containsKey($membership->serviceId()->toRfc4122());
    }

    public function service(Uuid $id): Service
    {
        return $this->services->get($id->toRfc4122()) ?? throw new LogicException('The service is open.');
    }

    public function stage(?Uuid $id): ?Stage
    {
        return $id === null ? null : $this->stages->get($id->toRfc4122());
    }
}
