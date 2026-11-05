<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\ValueObject\PlainPassword;
use SensitiveParameter;

interface PasswordHasherInterface
{
    public function hash(#[SensitiveParameter] PlainPassword $password): string;

    public function unusableHash(): string;
}
