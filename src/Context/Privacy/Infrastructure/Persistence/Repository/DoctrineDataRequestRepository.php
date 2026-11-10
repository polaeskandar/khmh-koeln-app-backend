<?php

declare(strict_types=1);

namespace App\Context\Privacy\Infrastructure\Persistence\Repository;

use App\Context\Privacy\Domain\Entity\DataRequest;
use App\Context\Privacy\Domain\Enum\RequestChannel;
use App\Context\Privacy\Domain\Enum\RequestKind;
use App\Context\Privacy\Domain\Enum\RequestStatus;
use App\Context\Privacy\Domain\Repository\DataRequestRepositoryInterface;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Model\LocalDate;
use App\Library\Types\Model\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineDataRequestRepository implements DataRequestRepositoryInterface
{
    private const string LOCK = 'SELECT id FROM privacy.data_request WHERE id = ? FOR UPDATE';

    private const string DUE = <<<'SQL'
        SELECT id::text FROM privacy.data_request
        WHERE status = :open AND kind = :erasure AND erases_on <= :day
        ORDER BY erases_on, id
        SQL;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function save(DataRequest $request): void
    {
        $this->entityManager->persist($request);
        $this->entityManager->flush();
    }

    #[Override]
    public function find(Uuid $id): ?DataRequest
    {
        return $this->entityManager->find(DataRequest::class, $id);
    }

    #[Override]
    public function openAppErasureOf(Uuid $accountId): ?DataRequest
    {
        return $this->entityManager
            ->getRepository(DataRequest::class)
            ->findOneBy([
                'subject.accountId' => $accountId,
                'kind' => RequestKind::Erasure,
                'receipt.channel' => RequestChannel::App,
                'status' => RequestStatus::Open,
            ]);
    }

    /**
     * @return list<DataRequest>
     */
    #[Override]
    public function all(): array
    {
        return $this->entityManager->getRepository(DataRequest::class)->findBy([], [
            'deadlines.dueOn' => 'ASC',
            'id' => 'ASC',
        ]);
    }

    /**
     * @return list<Uuid>
     */
    #[Override]
    public function erasuresDueOn(LocalDate $day): array
    {
        $ids = $this->entityManager
            ->getConnection()
            ->executeQuery(self::DUE, [
                'open' => RequestStatus::Open->value,
                'erasure' => RequestKind::Erasure->value,
                'day' => $day->toString(),
            ])
            ->fetchFirstColumn();

        return new ArrayCollection($ids)->map(static fn (mixed $id): Uuid => Uuid::fromString(
            Type::of($id)->toString(),
        ))->getValues();
    }

    #[Override]
    public function withWriteLock(Uuid $id, callable $work): mixed
    {
        return $this->entityManager->wrapInTransaction(function () use ($id, $work): mixed {
            $this->entityManager->getConnection()->executeQuery(self::LOCK, [$id->toRfc4122()]);

            return $work($this->entityManager->find(DataRequest::class, $id) ?? throw NotFound::of('request'));
        });
    }
}
