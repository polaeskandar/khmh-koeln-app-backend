<?php

declare(strict_types=1);

namespace App\Tests\Support\Interface\Api\ParentPortal;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\ValueObject\HomeworkText;
use App\Context\Learning\Domain\ValueObject\Materials;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Domain\Entity\Person;
use App\Library\Time\Model\LocalDate;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\ContainerInterface;

final readonly class ParentFixture
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    public function task(Lesson $lesson, string $title, ?string $dueDate, ?string $publishedAt): HomeworkAssignment
    {
        $task = new HomeworkAssignment(
            $lesson,
            HomeworkText::of($title, 'Read it.', $dueDate === null ? null : LocalDate::of($dueDate)),
            Materials::none(),
            createdBy: null,
        );

        if ($publishedAt !== null) {
            $task->changeState($task->state()->published(new DateTimeImmutable($publishedAt)));
        }

        new Database($this->container)->persisted($task);

        return $task;
    }

    public function child(string $name, Stage $stage): Person
    {
        $rosters = new Rosters($this->container);
        $child = $rosters->person($name);
        $rosters->place($child, $stage->service(), $stage, '2026-09-01');

        return $child;
    }

    public function otherLesson(): Lesson
    {
        $youth = Structure::service('Youth Meeting');
        $stage = Structure::stage($youth, 'Juniors');
        new Database($this->container)->persisted($youth, $stage);
        $schedule = new Schedules($this->container)->weekly($youth, 'Juniors meeting', 5, stage: $stage);

        return new Lessons($this->container)->stored($stage, $schedule, '2026-11-13', 'Other');
    }

    /**
     * @return array{Lesson, Person, Stage}
     */
    public function classWithMina(): array
    {
        $service = Structure::service();
        $third = Structure::stage($service);
        new Database($this->container)->persisted($service, $third);
        $class = new Schedules($this->container)->weekly($service, 'Sunday class', 7, stage: $third);
        $lesson = new Lessons($this->container)->stored($third, $class, '2026-11-15', passage: 'Luke 10:25-37');

        new Database($this->container)
            ->connection()
            ->update(
                'learning.lesson',
                ['notes' => 'Mina was sad', 'objectives' => 'Love your neighbour'],
                ['id' => $lesson->id()->toRfc4122()],
            );

        return [$lesson, $this->child('Mina', $third), $third];
    }
}
