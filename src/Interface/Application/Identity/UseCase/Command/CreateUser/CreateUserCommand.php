<?php

declare(strict_types=1);

namespace App\Interface\Application\Identity\UseCase\Command\CreateUser;

use App\Context\Identity\Domain\Enum\Language;
use SensitiveParameter;

final readonly class CreateUserCommand
{
    public function __construct(
        public string $email,
        public string $displayName,
        public Language $language,
        #[SensitiveParameter]
        public ?string $password,
    ) {
    }
}
