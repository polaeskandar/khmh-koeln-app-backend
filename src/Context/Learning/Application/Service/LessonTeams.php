<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Repository\LessonCollaboratorRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\LessonTeam;
use App\Context\Learning\Domain\ValueObject\TeamChoice;

final readonly class LessonTeams
{
    public function __construct(
        private LessonCollaboratorRepositoryInterface $collaborators,
    ) {
    }

    public function chosen(TeamChoice $team, ?Lesson $lesson): LessonTeam
    {
        $responsible = $team->responsible->chosen($lesson?->responsibleId());
        $current = $lesson === null ? [] : $this->collaborators->ofLessons([$lesson->id()])->idsOf($lesson);

        return new LessonTeam($responsible, $team->helpers->chosen($responsible, $current));
    }

    public function store(Lesson $lesson, LessonTeam $team): void
    {
        $this->collaborators->replace($lesson, $team->helpers);
    }
}
