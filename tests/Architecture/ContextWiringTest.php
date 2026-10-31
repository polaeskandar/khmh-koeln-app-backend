<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use App\Platform\Migration\Model\MigrationSet;
use Doctrine\Bundle\DoctrineBundle\Mapping\MappingDriver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ContextWiringTest extends KernelTestCase
{
    #[DataProvider('contexts')]
    public function testEachContextMapsTheEntitiesOfItsDomain(string $context): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $bundleDriver = $entityManager->getConfiguration()->getMetadataDriverImpl();
        self::assertInstanceOf(MappingDriver::class, $bundleDriver);
        $chain = $bundleDriver->getDriver();
        self::assertInstanceOf(MappingDriverChain::class, $chain);

        $driver = $chain->getDrivers()[sprintf('App\\Context\\%s\\Domain', $context)] ?? null;
        self::assertInstanceOf(AttributeDriver::class, $driver);
        self::assertContains(sprintf('%s/src/Context/%s/Domain', self::projectDir(), $context), $driver->getPaths());
    }

    #[DataProvider('contexts')]
    public function testEachContextHasItsOwnSchemaAndMigrationHistory(string $context): void
    {
        $set = MigrationSet::named(strtolower($context), self::projectDir());

        self::assertSame($context, $set->context);

        self::assertSame(
            sprintf('App\\Context\\%s\\Infrastructure\\Persistence\\Migrations', $context),
            $set->namespace,
        );

        self::assertSame(strtolower($context) . '_migration_versions', $set->versionTable());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function contexts(): iterable
    {
        foreach (SourceLayoutTest::CONTEXTS as $context) {
            yield $context => [$context];
        }
    }

    private static function projectDir(): string
    {
        return dirname(__DIR__, levels: 2);
    }
}
