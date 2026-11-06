<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\Organization\Domain\Repository\StageRepositoryInterface;
use App\Context\Organization\Domain\ValueObject\Description;
use App\Context\Organization\Domain\ValueObject\StageName;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\RuleViolation;

final readonly class StageChecks
{
    private const int DESCRIPTION_LENGTH = 500;

    public function __construct(
        private StageRepositoryInterface $stages,
    ) {
    }

    public static function check(string $name, ?string $description): void
    {
        InvalidInput::all(
            static fn (): StageName => StageName::of($name),
            static fn (): Description => self::description($description),
        );
    }

    public static function description(?string $description): Description
    {
        return Description::of($description, max: self::DESCRIPTION_LENGTH);
    }

    public static function ensureOpen(Service $service): void
    {
        if ($service->isArchived()) {
            throw new RuleViolation('services.archivedNoStageChanges', ['name' => $service->name()->value]);
        }
    }

    public function ensureFree(Service $service, StageName $name, ?Stage $stage): void
    {
        $holder = $this->stages->findInService($service, $name->key);

        if ($holder === null || $holder === $stage) {
            return;
        }

        $code = $holder->isArchived() ? 'stages.nameTakenArchived' : 'stages.nameTaken';

        throw InvalidInput::field('name', $code, ['name' => $holder->name()->value]);
    }
}
