<?php

declare(strict_types=1);

namespace App\Interface\Api\Auth\Response;

use App\Workflow\Identity\Model\ServiceOption;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class RegistrationServicesResponse
{
    /**
     * @param list<ServiceOptionResponse> $items
     */
    public function __construct(
        public array $items,
    ) {
    }

    /**
     * @param list<ServiceOption> $options
     */
    public static function of(array $options): self
    {
        return new self(new ArrayCollection($options)->map(ServiceOptionResponse::of(...))->getValues());
    }
}
