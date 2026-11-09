<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Learning\Domain\Entity;

use App\Context\Learning\Domain\Entity\HomeworkTask;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\ValueObject\HomeworkText;
use App\Context\Learning\Domain\ValueObject\Materials;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Domain\Entity\Person;
use App\Library\Time\Model\LocalDate;
use App\Tests\Support\Context\Identity\Domain\Entity\MemberLinks;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\DependencyInjection\ContainerInterface;

final readonly class CopticCourse
{
    /**
     * @param array<string, HomeworkTask> $tasks
     */
    private function __construct(
        private ContainerInterface $container,
        public Service $course,
        public Stage $level1,
        public Stage $third,
        public array $tasks,
    ) {
    }

    public static function seeded(ContainerInterface $container): self
    {
        $course = Structure::service('Coptic Language Course');
        $level1 = Structure::stage($course, 'Level 1');
        $school = Structure::service();
        $third = Structure::stage($school);
        new Database($container)->persisted($course, $level1, $school, $third);
        $lessons = new Lessons($container);
        $easter = $lessons->stored($level1, null, '2026-10-26', 'The Easter greeting');
        $letters = $lessons->stored($level1, null, '2026-11-02', 'The first eight letters');
        $words = $lessons->stored($level1, null, '2026-11-09', 'Reading short words');
        $samaritan = $lessons->stored($third, null, '2026-11-01', 'The Good Samaritan');

        return new self($container, $course, $level1, $third, [
            'greeting' => self::published($container, $easter, 'Say the Easter greeting at home', null),
            'letters' => self::published($container, $letters, 'Write the first eight letters', '2026-11-09'),
            'words' => self::draft($container, $words, 'Read five short words'),
            'samaritan' => self::published($container, $samaritan, 'Tell the story at home', null),
            'archived' => self::archived($container, $easter, 'Draw the Easter icon'),
        ]);
    }

    public function member(
        string $email,
        string $firstName,
        string $since = '2026-09-01',
        ?string $until = null,
    ): Person {
        $rosters = new Rosters($this->container);
        $person = $rosters->person($firstName, 'Boulos');
        $rosters->place($person, $this->course, $this->level1, $since, $until);
        $account = new SignInAccounts($this->container)->add($email);
        new MemberLinks($this->container)->link($account, $person->id()->toRfc4122());

        return $person;
    }

    public function task(string $name): HomeworkTask
    {
        return $this->tasks[$name] ?? throw new LogicException('No such task.');
    }

    public function publishMore(int $count): void
    {
        $lesson = $this->task('letters')->lesson();

        foreach (range(start: 1, end: $count) as $number) {
            self::published($this->container, $lesson, 'Practice ' . $number, null);
        }
    }

    public function report(string $task, Person $person, string $byEmail): void
    {
        $by = new Database($this->container)
            ->connection()
            ->fetchOne('SELECT id::text FROM identity.app_user WHERE email = ?', [$byEmail]);

        new Reports($this->container)->stored(
            $this->task($task),
            $person,
            '2026-11-01T10:00:00+01:00',
            by: \is_string($by) ? $by : null,
        );
    }

    private static function published(
        ContainerInterface $container,
        Lesson $lesson,
        string $title,
        ?string $dueDate,
    ): HomeworkTask {
        $task = self::draftOf($lesson, $title, $dueDate);
        $task->changeState($task->state()->published(new DateTimeImmutable('2026-10-27T09:00:00+01:00')));
        new Database($container)->persisted($task);

        return $task;
    }

    private static function archived(ContainerInterface $container, Lesson $lesson, string $title): HomeworkTask
    {
        $task = self::draftOf($lesson, $title, null);
        $task->changeState($task->state()->published(new DateTimeImmutable('2026-10-27T09:00:00+01:00')));
        $task->changeState($task->state()->archived(new DateTimeImmutable('2026-10-28T09:00:00+01:00')));
        new Database($container)->persisted($task);

        return $task;
    }

    private static function draft(ContainerInterface $container, Lesson $lesson, string $title): HomeworkTask
    {
        $task = self::draftOf($lesson, $title, null);
        new Database($container)->persisted($task);

        return $task;
    }

    private static function draftOf(Lesson $lesson, string $title, ?string $dueDate): HomeworkTask
    {
        $resources = $title === 'Write the first eight letters'
            ? Materials::of([[
                'label' => 'The Coptic alphabet (chart)',
                'url' => 'https://example.org/alphabet.pdf',
            ]], 'resources')
            : Materials::none();

        return new HomeworkTask(
            $lesson,
            HomeworkText::of($title, 'Practise at home.', $dueDate === null ? null : LocalDate::of($dueDate)),
            $resources,
            createdBy: null,
        );
    }
}
