<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Domain\Entity\Membership;
use App\Library\Collections\Model\Collections;
use App\Workflow\Organization\Model\ServiceReference;
use App\Workflow\Organization\Model\StageReference;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class Placements
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
     * @param ReadableCollection<int, Service> $services
     * @param ReadableCollection<int, Stage> $stages
     */
    public static function of(ReadableCollection $services, ReadableCollection $stages): self
    {
        return new self(
            Collections::firstPerKey($services, keyOf: self::serviceKey(...)),
            Collections::firstPerKey($stages, keyOf: self::stageKey(...)),
        );
    }

    /**
     * @param ReadableCollection<int, Membership> $memberships
     *
     * @return list<PersonGroup>
     */
    public function groupsOf(ReadableCollection $memberships): array
    {
        return Collections::mapPresent($memberships, $this->groupOf(...))->getValues();
    }

    public function groupOf(Membership $membership): ?PersonGroup
    {
        $service = $this->services->get($membership->serviceId()->toRfc4122());

        if ($service === null) {
            return null;
        }

        $stageId = $membership->stageId()?->toRfc4122();
        $stage = $stageId === null ? null : $this->stages->get($stageId);

        return new PersonGroup(ServiceReference::of($service), $stage === null ? null : StageReference::of($stage));
    }

    /**
     * @return non-empty-string
     */
    private static function serviceKey(Service $service): string
    {
        return $service->id()->toRfc4122();
    }

    /**
     * @return non-empty-string
     */
    private static function stageKey(Stage $stage): string
    {
        return $stage->id()->toRfc4122();
    }
}
