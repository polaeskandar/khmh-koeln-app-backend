<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Library\Problem\Exception\InvalidInput;
use Symfony\Component\Uid\Uuid;

final readonly class ResponsibleChoice
{
    public function __construct(
        public ?string $sent,
        public bool $isPreparer,
    ) {
    }

    public function chosen(?Uuid $current): Uuid
    {
        if ($this->sent === null || $current?->toRfc4122() === $this->sent) {
            return $current ?? throw self::refusal();
        }

        return $this->isPreparer ? Uuid::fromString($this->sent) : throw self::refusal();
    }

    private static function refusal(): InvalidInput
    {
        return InvalidInput::field('responsibleId', 'lessons.chooseResponsible');
    }
}
