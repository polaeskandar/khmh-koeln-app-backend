<?php

declare(strict_types=1);

namespace App\Context\Privacy\Infrastructure\Persistence\Repository;

use App\Context\Privacy\Domain\Entity\DataRequest;
use App\Context\Privacy\Domain\Enum\RequestChannel;
use App\Context\Privacy\Domain\Enum\RequestKind;
use App\Context\Privacy\Domain\Enum\RequestStatus;
use App\Context\Privacy\Domain\Repository\DataRequestRepositoryInterface;
use App\Library\Problem\Exception\NotFound;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineDataRequestRepository implements DataRequestRepositoryInterface
{
    private const string LOCK = 'SELECT id FROM privacy.data_request WHERE id = ? FOR UPDATE';

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
        return $this->entityManager->getRepository(DataRequest::class)->findBy([], ['dueOn' => 'ASC', 'id' => 'ASC']);
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
