<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Security;

use Override;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class InvalidApiToken extends AuthenticationException
{
    #[Override]
    public function getMessageKey(): string
    {
        return 'auth.unauthenticated';
    }
}
