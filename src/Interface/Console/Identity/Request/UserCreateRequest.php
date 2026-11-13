<?php

declare(strict_types=1);

namespace App\Interface\Console\Identity\Request;

use App\Workflow\Identity\Model\NewAccountDetails;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Option;

final class UserCreateRequest
{
    #[Argument(description: 'Username for signing in, compared in lower case')]
    public string $username = '';

    #[Argument(description: 'Email address, compared in lower case')]
    public string $email = '';

    #[Argument(description: 'Display name')]
    public string $name = '';

    #[Option(description: 'Read the password from stdin')]
    public bool $passwordStdin = false;

    #[Option(description: 'Assign this role (in the groups the account is assigned to, for a role limited to them)')]
    public ?string $role = null;

    #[Option(description: 'en, de or ar')]
    public string $language = 'de';

    public function details(): NewAccountDetails
    {
        return new NewAccountDetails($this->username, $this->email, $this->name, $this->language);
    }
}
