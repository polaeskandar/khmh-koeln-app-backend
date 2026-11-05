<?php

declare(strict_types=1);

namespace App\Context\Privacy\Infrastructure\Persistence\Repository;

use App\Context\Privacy\Domain\Entity\DataRequest;
use App\Context\Privacy\Domain\Repository\DataRequestRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineDataRequestRepository implements DataRequestRepositoryInterface
{
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

    /**
     * @return list<DataRequest>
     */
    #[Override]
    public function all(): array
    {
        return $this->entityManager->getRepository(DataRequest::class)->findBy([], ['dueOn' => 'ASC', 'id' => 'ASC']);
    }
}
