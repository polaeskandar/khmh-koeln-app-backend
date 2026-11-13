<?php

declare(strict_types=1);

namespace App\Interface\Console\Identity\Request;

use Symfony\Component\Console\Attribute\Option;

final class UserCreateRequest
{
    #[Option(description: 'Read the password from stdin')]
    public bool $passwordStdin = false;

    #[Option(description: 'Assign this role (in the groups the account is assigned to, for a role limited to them)')]
    public ?string $role = null;

    #[Option(description: 'en, de or ar')]
    public string $language = 'de';
}
