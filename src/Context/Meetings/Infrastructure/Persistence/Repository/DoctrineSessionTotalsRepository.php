<?php

declare(strict_types=1);

namespace App\Context\Meetings\Infrastructure\Persistence\Repository;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Repository\SessionTotalsRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\ErasedCounts;
use App\Library\Types\Model\ListOf;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineSessionTotalsRepository implements SessionTotalsRepositoryInterface
{
    private const string ADD_ERASED = <<<'SQL'
        UPDATE meetings.meeting_session
        SET erased_roster = erased_roster + ?,
            erased_present = erased_present + ?,
            erased_roster_present = erased_roster_present + ?
        WHERE id = ?
        SQL;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param list<Schedule> $schedules
     * @param list<Uuid> $sessionIds
     */
    #[Override]
    public function ofSchedulesOrIds(array $schedules, array $sessionIds): ReadableCollection
    {
        return new ArrayCollection([...$this->ofSchedules($schedules), ...$this->withIds($sessionIds)]);
    }

    #[Override]
    public function addErased(Uuid $sessionId, ErasedCounts $share): void
    {
        $this->entityManager->getConnection()->executeStatement(self::ADD_ERASED, [
            $share->roster,
            $share->present,
            $share->rosterPresent,
            $sessionId->toRfc4122(),
        ]);
    }

    /**
     * @param list<Schedule> $schedules
     *
     * @return list<Session>
     */
    private function ofSchedules(array $schedules): array
    {
        if ($schedules === []) {
            return [];
        }

        $query = $this->entityManager
            ->createQueryBuilder()
            ->select('s')
            ->from(Session::class, 's')
            ->where('s.schedule IN (:schedules)')
            ->setParameter('schedules', $schedules)
            ->getQuery();

        return ListOf::instances($query->getResult(), Session::class);
    }

    /**
     * @param list<Uuid> $ids
     *
     * @return list<Session>
     */
    private function withIds(array $ids): array
    {
        return $ids === [] ? [] : $this->entityManager->getRepository(Session::class)->findBy(['id' => $ids]);
    }
}
