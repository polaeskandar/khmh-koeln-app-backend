<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Learning\Domain\Entity;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\HomeworkCompletion;
use App\Context\People\Domain\Entity\Person;
use App\Tests\Support\Database;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class Reports
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    public function stored(
        HomeworkAssignment $task,
        Person $child,
        string $at = '2026-11-07T10:00:00+01:00',
        ?string $undoneAt = null,
        ?string $by = null,
    ): HomeworkCompletion {
        $report = new HomeworkCompletion(
            $task,
            $child->id(),
            new DateTimeImmutable($at),
            $by === null ? null : Uuid::fromString($by),
        );

        if ($undoneAt !== null) {
            $report->undo(new DateTimeImmutable($undoneAt), by: null);
        }

        new Database($this->container)->persisted($report);

        return $report;
    }
}
