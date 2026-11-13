<?php

declare(strict_types=1);

namespace App\Tests\Support\Interface\Console\Privacy;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Enum\HomeworkStatus;
use App\Context\Learning\Domain\ValueObject\HomeworkText;
use App\Context\Learning\Domain\ValueObject\Materials;
use App\Context\People\Domain\Entity\Person;
use App\Context\Scheduling\Domain\Entity\Attendance;
use App\Context\Scheduling\Domain\ValueObject\Removal;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Context\Learning\Domain\Entity\Reports;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Context\Scheduling\Domain\Entity\Schedules;
use App\Tests\Support\Database;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\ContainerInterface;

final readonly class PrivacyFixture
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    /**
     * @return array{Person, string}
     */
    public function family(): array
    {
        $service = Structure::service();
        $third = Structure::stage($service);
        new Database($this->container)->persisted($service, $third);
        $schedules = new Schedules($this->container);
        $class = $schedules->weekly($service, 'Sunday class', 7, stage: $third);
        $session = $schedules->session($class, '2026-11-08');
        $rosters = new Rosters($this->container);
        $mina = $rosters->person('Mina', 'Hanna');
        $rosters->place($mina, $service, $third, '2026-09-01');
        $families = new Families($this->container);

        $parent = $families->account('hoda@example.org', [
            'display_name' => 'Hoda Farag',
            'privacy_accepted_at' => '2026-10-30 10:00:00+01',
            'privacy_version' => '2026-11',
        ]);

        new RoleGrants($this->container)->grant($parent, 'parent');
        $families->link($parent, $mina);

        $mark = new Attendance(
            $schedules->managed($session),
            $mina->id(),
            new DateTimeImmutable('2026-11-08T12:00:00+01:00'),
            recordedBy: null,
        );

        $mark->remove(Removal::of(new DateTimeImmutable('2026-11-08T12:05:00+01:00'), by: null, reason: 'Wrong child'));
        $lesson = new Lessons($this->container)->stored($third, $class, '2026-11-15');

        $task = new HomeworkAssignment(
            $lesson,
            HomeworkText::of('Learn the verse', 'Read it.', null),
            Materials::none(),
            null,
        );

        $task->changeState(
            $task
                ->state()
                ->withStatus(HomeworkStatus::Published)
                ->withPublishedAt(new DateTimeImmutable('2026-11-01T09:00:00+01:00')),
        );

        new Database($this->container)->persisted($mark, $task);
        new Reports($this->container)->stored($task, $mina, by: $parent);

        return [$mina, $parent];
    }
}
