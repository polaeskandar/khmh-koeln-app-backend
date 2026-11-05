<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Entity\Stage;
use App\Context\Organization\Domain\Repository\StageRepositoryInterface;
use App\Library\Problem\Exception\InvalidInput;

final readonly class StageNames
{
    public function __construct(
        private StageRepositoryInterface $stages,
    ) {
    }

    public function ensureFreeFor(Stage $stage): void
    {
        $holder = $this->stages->findInService($stage->service(), $stage->name()->key);

        if ($holder === null || $holder === $stage) {
            return;
        }

        $code = $holder->isArchived() ? 'stages.nameTakenArchived' : 'stages.nameTaken';

        throw InvalidInput::field('name', $code, ['name' => $holder->name()->value]);
    }
}
