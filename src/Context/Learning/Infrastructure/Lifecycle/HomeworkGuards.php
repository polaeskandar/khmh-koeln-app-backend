<?php

declare(strict_types=1);

namespace App\Context\Learning\Infrastructure\Lifecycle;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Repository\HomeworkReportRepositoryInterface;
use App\Library\Types\Model\Type;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\TransitionBlocker;

final readonly class HomeworkGuards
{
    private const string LESSON_ARCHIVED = 'homework.lessonArchived';

    private const string ALREADY_REPORTED = 'homework.alreadyReported';

    public function __construct(
        private HomeworkReportRepositoryInterface $reports,
    ) {
    }

    #[AsGuardListener(workflow: 'homework_lifecycle', transition: 'publish')]
    #[AsGuardListener(workflow: 'homework_lifecycle', transition: 'restore_as_draft')]
    #[AsGuardListener(workflow: 'homework_lifecycle', transition: 'restore_as_published')]
    public function blockArchivedLesson(GuardEvent $event): void
    {
        if (self::task($event)->lesson()->state()->archivedAt === null) {
            return;
        }

        $event->addTransitionBlocker(new TransitionBlocker(self::LESSON_ARCHIVED, self::LESSON_ARCHIVED));
    }

    #[AsGuardListener(workflow: 'homework_lifecycle', transition: 'unpublish')]
    public function blockReportedTask(GuardEvent $event): void
    {
        if ($this->reports->reportedAmong([self::task($event)->id()])->isEmpty()) {
            return;
        }

        $event->addTransitionBlocker(new TransitionBlocker(self::ALREADY_REPORTED, self::ALREADY_REPORTED));
    }

    private static function task(GuardEvent $event): HomeworkAssignment
    {
        return Type::of($event->getSubject())->toInstanceOf(HomeworkAssignment::class);
    }
}
