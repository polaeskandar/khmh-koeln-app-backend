<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Response;

final readonly class RegistrationReceivedResponse
{
    public function __construct(
        public string $status = 'received',
    ) {
    }
}
