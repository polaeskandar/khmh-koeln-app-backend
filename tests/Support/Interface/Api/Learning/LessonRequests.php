<?php

declare(strict_types=1);

namespace App\Tests\Support\Interface\Api\Learning;

use App\Context\Organization\Domain\Entity\Stage;
use App\Context\Scheduling\Domain\Entity\Schedule;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\Scheduling\Domain\Entity\Schedules;
use App\Tests\Support\Database;
use Symfony\Component\DependencyInjection\ContainerInterface;

final readonly class LessonRequests
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    /**
     * @param array<string, mixed> $changes
     *
     * @return array<string, mixed>
     */
    public static function body(Schedule $class, string $date, string $responsibleId, array $changes = []): array
    {
        return array_replace_recursive([
            'details' => ['title' => 'The Good Samaritan', 'passage' => 'Luke 10:25-37'],
            'meeting' => ['scheduleId' => $class->id()->toRfc4122(), 'date' => $date],
            'plan' => ['objectives' => 'Love your neighbour'],
            'responsibleId' => $responsibleId,
        ], $changes);
    }

    public static function lessonsOf(Stage $stage): string
    {
        return '/api/stages/' . $stage->id()->toRfc4122() . '/lessons';
    }

    /**
     * @return array{Stage, Schedule}
     */
    public function thirdClass(): array
    {
        $service = Structure::service();
        $third = Structure::stage($service);
        new Database($this->container)->persisted($service, $third);

        return [$third, new Schedules($this->container)->weekly($service, 'Sunday class', 7, stage: $third)];
    }

    /**
     * @return list<mixed>
     */
    public function stored(string $id): array
    {
        $row = new Database($this->container)
            ->connection()
            ->fetchNumeric('SELECT title, summary, teaching_date::text FROM learning.lesson WHERE id = ?', [$id]);

        return $row === false ? [] : $row;
    }
}
