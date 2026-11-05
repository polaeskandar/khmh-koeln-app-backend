<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Privacy\Domain\ValueObject\ErasedSubject;

final readonly class ErasedSubjectView
{
    public function __construct(
        public string $kind,
        public ?string $group,
    ) {
    }

    public static function of(ErasedSubject $erased): ?self
    {
        return $erased->kind === null ? null : new self($erased->kind->value, $erased->group);
    }
}
