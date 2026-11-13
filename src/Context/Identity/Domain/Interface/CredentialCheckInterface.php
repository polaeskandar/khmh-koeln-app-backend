<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Interface;

use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

interface CredentialCheckInterface
{
    public function accountWith(string $username, #[SensitiveParameter] string $password): ?Uuid;
}
