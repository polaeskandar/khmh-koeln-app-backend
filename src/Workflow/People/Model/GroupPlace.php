<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

use App\Context\People\Domain\Entity\Membership;
use App\Workflow\Organization\Model\ServiceReference;
use App\Workflow\Organization\Model\StageSummary;

final readonly class GroupPlace
{
    public function __construct(
        public string $id,
        public ServiceReference $service,
        public ?StageSummary $stage,
        public string $since,
    ) {
    }

    public static function of(Membership $membership, OpenGroups $groups): self
    {
        $stage = $groups->stage($membership->stageId());

        return new self(
            $membership->id()->toRfc4122(),
            ServiceReference::of($groups->service($membership->serviceId())),
            $stage === null ? null : StageSummary::of($stage),
            $membership->tenure()->startsOn->toString(),
        );
    }
}
