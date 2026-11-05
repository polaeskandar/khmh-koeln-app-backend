<?php

declare(strict_types=1);

namespace App\Context\Meetings\Infrastructure\Persistence\Repository;

use App\Context\Meetings\Domain\Repository\AccountTraceRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineAccountTraceRepository implements AccountTraceRepositoryInterface
{
    public const array ACTOR_COLUMNS = [
        ['meetings.meeting_session', 'opened_by'],
        ['meetings.meeting_session', 'closed_by'],
        ['meetings.session_event',   'actor_id'],
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function forget(Uuid $accountId): int
    {
        $connection = $this->entityManager->getConnection();
        $id = $accountId->toRfc4122();

        return new ArrayCollection(self::ACTOR_COLUMNS)->reduce(
            /**
             * @param array{string, string} $actor
             */
            static fn (int $kept, array $actor): int => $kept
            + (int) $connection->update($actor[0], [$actor[1] => null], [$actor[1] => $id]),
            initial: 0,
        );
    }
}
