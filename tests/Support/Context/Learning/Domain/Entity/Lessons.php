<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Learning\Domain\Entity;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\ValueObject\LessonText;
use App\Context\Learning\Domain\ValueObject\MeetingLink;
use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Organization\Domain\Entity\Stage;
use App\Library\Time\Model\LocalDate;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use Symfony\Component\DependencyInjection\ContainerInterface;

final readonly class Lessons
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    public function stored(
        Stage $stage,
        ?Schedule $schedule,
        string $date,
        string $title = 'The Good Samaritan',
        ?string $passage = null,
    ): Lesson {
        $lesson = new Lesson(
            $stage->id(),
            new MeetingLink($schedule?->id(), LocalDate::of($date)),
            new LessonText($title, passage: $passage),
            responsibleId: null,
        );

        new Database($this->container)->persisted($lesson);

        return $lesson;
    }

    public function inThirdClass(string $date = '2026-11-15'): Lesson
    {
        $service = Structure::service();
        $third = Structure::stage($service);
        new Database($this->container)->persisted($service, $third);
        $class = new Schedules($this->container)->weekly($service, 'Sunday class', 7, stage: $third);

        return $this->stored($third, $class, $date);
    }
}
