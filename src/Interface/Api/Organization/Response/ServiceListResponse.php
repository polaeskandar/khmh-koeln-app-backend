<?php

declare(strict_types=1);

namespace App\Interface\Api\Organization\Response;

use App\Workflow\Organization\Model\ServiceList;
use App\Workflow\Organization\Model\ServiceOverview;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class ServiceListResponse
{
    /**
     * @param list<ServiceResponse> $items
     */
    public function __construct(
        public array $items,
    ) {
    }

    public static function of(ServiceList $list): self
    {
        return new self(
            new ArrayCollection($list->items)->map(
                static fn (ServiceOverview $overview): ServiceResponse => new ServiceResponse($overview),
            )->getValues(),
        );
    }
}
