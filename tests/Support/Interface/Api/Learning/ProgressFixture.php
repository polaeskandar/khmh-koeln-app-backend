<?php

declare(strict_types=1);

namespace App\Tests\Support\Interface\Api\Learning;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\HomeworkCompletion;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Enum\HomeworkStatus;
use App\Context\Learning\Domain\ValueObject\HomeworkText;
use App\Context\Learning\Domain\ValueObject\Materials;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Domain\Entity\Person;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use DateTimeImmutable;
use PHPUnit\Framework\Assert;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class ProgressFixture
{
    private const array LAST_NAMES = [
        'Bishoy',
        'Ägid',
        'Adel',
        'Daoud',
        'Ebeid',
        'Fahmy',
        'Girgis',
        'Labib',
        'Makram',
        'Nashed',
        'Rizk',
        'Tawfik',
        'Zaki',
    ];

    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    /**
     * @return array{HomeworkAssignment, Person, string}
     */
    public function create(): array
    {
        $lesson = new Lessons($this->container)->inThirdClass();

        $stage = new Database($this->container)
            ->entityManager()
            ->find(Stage::class, $lesson->stageId());

        Assert::assertInstanceOf(Stage::class, $stage);
        $rosters = new Rosters($this->container);
        $mina = $rosters->person('Mina', 'Hanna');
        $rosters->place($mina, $stage->service(), $stage, '2026-09-01');

        foreach (self::LAST_NAMES as $lastName) {
            $rosters->place($rosters->person('Child', $lastName), $stage->service(), $stage, '2026-09-01');
        }

        $former = $rosters->person('Former', 'Youssef');
        $rosters->place($former, $stage->service(), $stage, '2026-09-01', '2026-10-31');
        $families = new Families($this->container);
        $hoda = $families->account('hoda@example.org', ['display_name' => 'Hoda Farag']);
        $families->link($hoda, $mina);
        $families->link($families->account('father@example.org'), $mina);
        $task = $this->publishedTask($lesson);
        $this->report($task, $mina, '2026-11-08T12:08:00+01:00', $hoda);
        $this->report($task, $former, '2026-10-30T10:00:00+01:00', null);

        return [$task, $mina, $hoda];
    }

    private function publishedTask(Lesson $lesson): HomeworkAssignment
    {
        $task = new HomeworkAssignment(
            $lesson,
            HomeworkText::of('Learn the verse', 'Read it twice.', null),
            Materials::none(),
            createdBy: null,
        );

        $task->changeState(
            $task
                ->state()
                ->withStatus(HomeworkStatus::Published)
                ->withPublishedAt(new DateTimeImmutable('2026-11-01T09:00:00+01:00')),
        );

        new Database($this->container)->persisted($task);

        return $task;
    }

    private function report(HomeworkAssignment $task, Person $child, string $at, ?string $by): void
    {
        new Database($this->container)->persisted(
            new HomeworkCompletion(
                $task,
                $child->id(),
                new DateTimeImmutable($at),
                $by === null ? null : Uuid::fromString($by),
            ),
        );
    }
}
