<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Repository\LessonCollaboratorRepositoryInterface;
use App\Context\Learning\Domain\Repository\LessonRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\LessonTeam;
use App\Context\Learning\Domain\ValueObject\TeamChoice;
use App\Library\Collections\Model\Collections;
use App\Platform\Doctrine\Contract\TransactionsInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class LessonTeams
{
    public function __construct(
        private LessonCollaboratorRepositoryInterface $collaborators,
        private LessonRepositoryInterface $lessons,
        private TransactionsInterface $transactions,
    ) {
    }

    public function chosen(TeamChoice $team, ?Lesson $lesson): LessonTeam
    {
        $responsible = $team->responsible->chosen($lesson?->responsibleId());
        $current = $lesson === null ? [] : $this->collaborators->ofLessons([$lesson->id()])->idsOf($lesson);
        $helpers = $team->helpers->chosen($responsible, $current);
        $chosenIds = new ArrayCollection($helpers)->map(static fn (Uuid $id): string => $id->toRfc4122());

        return new LessonTeam($responsible, $helpers, self::sorted($chosenIds->getValues()) !== self::sorted($current));
    }

    public function savedWith(Lesson $lesson, LessonTeam $team): Lesson
    {
        if ($team->helpersChanged) {
            $lesson->helpersChanged();
        }

        return $this->transactions->run(function () use ($lesson, $team): Lesson {
            $this->lessons->save($lesson);
            $this->collaborators->replace($lesson, $team->helpers);

            return $lesson;
        });
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private static function sorted(array $ids): array
    {
        return Collections::sortedBy(new ArrayCollection($ids), keyOf: static fn (string $id): string => $id);
    }
}
