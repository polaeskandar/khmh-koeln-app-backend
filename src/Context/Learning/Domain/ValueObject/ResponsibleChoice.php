<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Library\Problem\Exception\InvalidInput;
use Symfony\Component\Uid\Uuid;

final readonly class ResponsibleChoice
{
    public function __construct(
        public ?Uuid $sent,
        public bool $isPreparer,
    ) {
    }

    public function chosen(?Uuid $current): Uuid
    {
        $sent = $this->sent;
        $allowed = $this->isPreparer || $current?->equals($sent) === true;

        return $allowed && $sent !== null
            ? $sent
            : throw InvalidInput::field('responsibleId', 'lessons.chooseResponsible');
    }
}
