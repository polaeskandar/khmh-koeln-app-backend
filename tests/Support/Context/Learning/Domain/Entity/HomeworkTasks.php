<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Learning\Domain\Entity;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\HomeworkCompletion;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\ValueObject\HomeworkText;
use App\Context\Learning\Domain\ValueObject\Materials;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Domain\Entity\Person;
use App\Library\Time\Model\LocalDate;
use App\Tests\Support\Database;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\DependencyInjection\ContainerInterface;

final readonly class HomeworkTasks
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    public function draft(Lesson $lesson, ?string $dueDate): HomeworkAssignment
    {
        $task = new HomeworkAssignment(
            $lesson,
            HomeworkText::of('Learn the verse', 'Read it twice.', $dueDate === null ? null : LocalDate::of($dueDate)),
            Materials::none(),
            createdBy: null,
        );

        new Database($this->container)->persisted($task);

        return $task;
    }

    public function report(HomeworkAssignment $task, Person $child, string $at, ?string $undone = null): void
    {
        $report = new HomeworkCompletion($task, $child->id(), new DateTimeImmutable($at), completedBy: null);

        if ($undone !== null) {
            $report->undo(new DateTimeImmutable($undone), by: null);
        }

        new Database($this->container)->persisted($report);
    }

    /**
     * @return array{Service, Stage}
     */
    public function stageOf(HomeworkAssignment $task): array
    {
        $stage = new Database($this->container)
            ->entityManager()
            ->find(Stage::class, $task->stageId());

        return (
            $stage instanceof Stage
                ? [$stage->service(), $stage]
                : throw new LogicException('A homework task has a stage.')
        );
    }
}
