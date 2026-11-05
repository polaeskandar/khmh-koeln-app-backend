<?php

declare(strict_types=1);

namespace App\Interface\Console\Identity\Request;

use Symfony\Component\Console\Attribute\Option;

final class UserCreateRequest
{
    #[Option(description: 'Set the password later by link')]
    public bool $noPassword = false;

    #[Option(description: 'Read the password from stdin')]
    public bool $passwordStdin = false;

    #[Option(description: 'Assign this role everywhere')]
    public ?string $role = null;

    #[Option(description: 'en, de or ar')]
    public string $language = 'de';
}
