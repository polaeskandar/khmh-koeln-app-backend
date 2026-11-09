<?php

declare(strict_types=1);

namespace App\Platform\Doctrine\Service;

use App\Library\Problem\Exception\RuleViolation;
use App\Platform\Doctrine\Contract\VersionChecksInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Override;

final readonly class DoctrineVersionChecks implements VersionChecksInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function ensureCurrent(object $entity, ?int $version, string $refusal): void
    {
        if ($version === null) {
            return;
        }

        try {
            $this->entityManager->lock($entity, LockMode::OPTIMISTIC, $version);
        } catch (OptimisticLockException) {
            throw new RuleViolation($refusal);
        }
    }
}
