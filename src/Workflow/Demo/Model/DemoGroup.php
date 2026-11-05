<?php

declare(strict_types=1);

namespace App\Workflow\Demo\Model;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class DemoGroup
{
    public function __construct(
        public string $name,
        public string $serviceId,
        public ?string $stageId,
    ) {
    }

    /**
     * @param list<Service|Stage> $groups
     *
     * @return list<self>
     */
    public static function listOf(array $groups): array
    {
        return new ArrayCollection($groups)->map(self::of(...))->getValues();
    }

    public static function of(Service|Stage $group): self
    {
        return $group instanceof Stage
            ? new self($group->name()->value, $group->service()->id()->toRfc4122(), $group->id()->toRfc4122())
            : new self($group->name()->value, $group->id()->toRfc4122(), stageId: null);
    }
}
