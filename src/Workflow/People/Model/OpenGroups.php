<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

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
     * @param ReadableCollection<int, Service> $services the memberships' services
     * @param ReadableCollection<int, Stage> $stages the memberships' stages
     */
    public static function of(ReadableCollection $services, ReadableCollection $stages): self
    {
        $services = $services->filter(static fn (Service $one): bool => !$one->isArchived());
        $stages = $stages->filter(static fn (Stage $one): bool => !$one->isArchived());

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
