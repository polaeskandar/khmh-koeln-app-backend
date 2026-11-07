<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

use App\Context\Organization\Domain\ValueObject\ChosenPlacement;
use App\Context\People\Domain\Entity\Membership;
use App\Workflow\Organization\Model\ServiceReference;
use App\Workflow\Organization\Model\StageReference;

final readonly class AddedMembership
{
    public function __construct(
        public string $id,
        public string $personId,
        public ServiceReference $service,
        public ?StageReference $stage,
        public string $startsOn,
    ) {
    }

    public static function of(Membership $membership, ChosenPlacement $placement): self
    {
        $stage = $placement->stage;

        return new self(
            id: $membership->id()->toRfc4122(),
            personId: $membership->person()->id()->toRfc4122(),
            service: ServiceReference::of($placement->service),
            stage: $stage === null ? null : StageReference::of($stage),
            startsOn: $membership->tenure()->startsOn->toString(),
        );
    }
}
