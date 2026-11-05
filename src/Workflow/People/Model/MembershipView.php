<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

use App\Context\People\Domain\Entity\Membership;

final readonly class MembershipView
{
    public function __construct(
        public string $id,
        public string $serviceId,
        public ?string $stageId,
        public string $startsOn,
        public ?string $endsOn,
    ) {
    }

    public static function of(Membership $membership): self
    {
        $tenure = $membership->tenure();

        return new self(
            $membership->id()->toRfc4122(),
            $membership->serviceId()->toRfc4122(),
            $membership->stageId()?->toRfc4122(),
            $tenure->startsOn->toString(),
            $tenure->endsOn?->toString(),
        );
    }
}
