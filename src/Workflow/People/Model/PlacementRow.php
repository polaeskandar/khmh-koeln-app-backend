<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

use App\Context\People\Domain\Entity\Membership;
use App\Workflow\Organization\Model\ServiceReference;
use App\Workflow\Organization\Model\StageReference;

final readonly class PlacementRow
{
    public function __construct(
        public string $membershipId,
        public ServiceReference $service,
        public ?StageReference $stage,
        public string $startsOn,
        public ?string $endsOn,
    ) {
    }

    public static function of(Membership $membership, Placements $placements): ?self
    {
        $group = $placements->groupOf($membership);

        if ($group === null) {
            return null;
        }

        $tenure = $membership->tenure();

        return new self(
            $membership->id()->toRfc4122(),
            $group->service,
            $group->stage,
            $tenure->startsOn->toString(),
            $tenure->endsOn?->toString(),
        );
    }
}
