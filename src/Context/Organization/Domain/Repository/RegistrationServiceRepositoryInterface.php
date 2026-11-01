<?php

declare(strict_types=1);

namespace App\Context\Organization\Domain\Repository;

use App\Context\Organization\Domain\Entity\RegistrationService;

interface RegistrationServiceRepositoryInterface
{
    public function add(RegistrationService $registrationService): void;
}
