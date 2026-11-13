<?php

declare(strict_types=1);

namespace App\Context\Access\Infrastructure\Persistence\Repository;

use App\Context\Access\Domain\Entity\Permission;
use App\Context\Access\Domain\Repository\PermissionRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrinePermissionRepository implements PermissionRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function catalog(): ReadableCollection
    {
        return new ArrayCollection($this->entityManager->getRepository(Permission::class)->findAll());
    }
}
