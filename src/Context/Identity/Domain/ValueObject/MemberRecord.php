<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\ValueObject;

use App\Library\Problem\Exception\InvalidInput;
use Symfony\Component\Uid\Uuid;

final readonly class MemberRecord
{
    public function __construct(
        public Uuid $personId,
        public string $name,
    ) {
    }

    public function taken(): InvalidInput
    {
        return InvalidInput::field('personId', 'accounts.personHasAccount', ['name' => $this->name]);
    }
}
