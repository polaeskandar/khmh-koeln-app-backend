<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\ValueObject;

use App\Context\Identity\Domain\Enum\Language;
use Symfony\Component\Uid\Uuid;

final readonly class AccountDraft
{
    public function __construct(
        public Uuid $personId,
        public string $username,
        public Language $language,
    ) {
    }
}
