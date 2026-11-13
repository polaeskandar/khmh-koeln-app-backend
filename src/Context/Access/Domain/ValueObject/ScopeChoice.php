<?php

declare(strict_types=1);

namespace App\Context\Access\Domain\ValueObject;

use App\Context\Access\Domain\Enum\RoleScope;
use App\Library\Problem\Exception\InvalidInput;

final readonly class ScopeChoice
{
    private function __construct(
        private ?RoleScope $scope,
    ) {
    }

    public static function of(?string $scope): self
    {
        if ($scope === null) {
            return new self(null);
        }

        return new self(RoleScope::tryFrom($scope) ?? throw InvalidInput::field('scope', 'validation.invalidChoice'));
    }

    public function orElse(RoleScope $kept): RoleScope
    {
        return $this->scope ?? $kept;
    }
}
