<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Learning\Domain\Entity;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\ValueObject\LessonText;
use App\Context\Learning\Domain\ValueObject\MeetingLink;
use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Organization\Domain\Entity\Stage;
use App\Library\Time\Model\LocalDate;
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
    ): Lesson {
        $lesson = new Lesson(
            $stage->id(),
            new MeetingLink($schedule?->id(), LocalDate::of($date)),
            new LessonText($title),
            responsibleId: null,
        );

        new Database($this->container)->persisted($lesson);

        return $lesson;
    }
}
