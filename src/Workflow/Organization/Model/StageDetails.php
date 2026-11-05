<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Organization\Domain\Entity\Stage;

final readonly class StageDetails
{
    public function __construct(
        public StageEntry $stage,
        public bool $archived,
        public ServiceReference $service,
        public StageAbilities $can,
    ) {
    }

    public static function of(Stage $stage, AccessCheck $access): self
    {
        return new self(
            StageEntry::of($stage),
            $stage->isArchived(),
            ServiceReference::of($stage->service()),
            StageAbilities::of($stage, $access),
        );
    }
}
