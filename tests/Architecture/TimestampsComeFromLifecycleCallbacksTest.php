<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use App\Library\Persistence\Mapping\CreatedAtEntity;
use App\Library\Persistence\Mapping\TimestampedEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TimestampsComeFromLifecycleCallbacksTest extends KernelTestCase
{
    private const array STAMPS = ['createdAt' => CreatedAtEntity::class, 'updatedAt' => TimestampedEntity::class];

    public function testEveryCreatedAtAndUpdatedAtIsStampedByItsBaseClass(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        foreach ($entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            foreach (self::STAMPS as $field => $base) {
                if (!$metadata->hasField($field)) {
                    continue;
                }

                self::assertSame(
                    $base,
                    $metadata->getFieldMapping($field)->declared ?? $metadata->getName(),
                    sprintf('%s::$%s must come from %s.', $metadata->getName(), $field, $base),
                );
            }
        }
    }
}
