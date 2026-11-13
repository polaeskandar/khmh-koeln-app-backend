<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Domain\ValueObject\AccountErased;
use App\Library\Strings\Model\Text;

final readonly class ErasureCounts
{
    public function __construct(
        public int $roles,
        public int $guardianLinks,
        public int $rowsKept,
    ) {
    }

    public static function of(AccountErased $erased, int $guardianLinks): self
    {
        return new self($erased->roles, $guardianLinks, $erased->rowsKept);
    }

    public function summary(): string
    {
        return Text::format(
            '%d roles and %d guardian links removed; %d rows kept without the account.',
            $this->roles,
            $this->guardianLinks,
            $this->rowsKept,
        )->toString();
    }
}
