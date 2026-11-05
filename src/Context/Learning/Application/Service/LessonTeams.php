<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Repository\LessonCollaboratorRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\LessonTeam;
use App\Context\Learning\Domain\ValueObject\TeamChoice;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class LessonTeams
{
    public function __construct(
        private LessonCollaboratorRepositoryInterface $collaborators,
    ) {
    }

    public function chosen(TeamChoice $team, ?Lesson $lesson): LessonTeam
    {
        $responsible = $team->responsible->chosen($lesson?->responsibleId());
        $current = $lesson === null ? [] : $this->helpersOf([$lesson])[$lesson->id()->toRfc4122()] ?? [];

        return new LessonTeam($responsible, $team->helpers->chosen($responsible, $current));
    }

    public function store(Lesson $lesson, LessonTeam $team): void
    {
        if ($team->helpers === null) {
            return;
        }

        $this->collaborators->replace($lesson, $team->helpers);
    }

    /**
     * @param list<Lesson> $lessons
     *
     * @return array<string, list<string>>
     */
    public function helpersOf(array $lessons): array
    {
        return $this->collaborators->ofLessons(
            new ArrayCollection($lessons)->map(static fn (Lesson $lesson): Uuid => $lesson->id())->getValues(),
        );
    }
}
