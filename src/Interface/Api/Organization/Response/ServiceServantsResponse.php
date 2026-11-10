<?php

declare(strict_types=1);

namespace App\Interface\Api\Organization\Response;

use App\Workflow\Identity\Model\AccountReference;
use App\Workflow\Organization\Model\ServiceServant;
use App\Workflow\Organization\Model\ServiceServants;

final readonly class ServiceServantsResponse
{
    /**
     * @param list<ServiceServant> $servants
     * @param list<AccountReference> $assignable
     */
    public function __construct(
        public array $servants,
        public array $assignable,
    ) {
    }

    public static function of(ServiceServants $servants): self
    {
        return new self($servants->servants, $servants->assignable);
    }
}
