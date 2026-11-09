<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

final readonly class RegistrationChoice
{
    /**
     * @param list<string> $serviceIds
     */
    public function __construct(
        public string $registeredAs,
        public string $note,
        public array $serviceIds,
        public string $privacyVersion,
    ) {
    }
}
