<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Organization\Domain\Entity;

use App\Tests\Support\Context\Organization\Domain\Entity\OrganizationDatabase;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class ChurchServiceTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    public function testTheClockStampsCreationAndEveryUpdate(): void
    {
        self::mockTime('2026-11-08 12:00:00+01:00');
        $service = Structure::service();
        $this->database()->persisted($service);

        self::mockTime('2026-11-09 08:30:00+01:00');
        $service->archive(Structure::now());
        $this->database()->persisted($service);

        self::assertSame('2026-11-08T12:00:00+01:00', $service->createdAt()->format(DATE_RFC3339));
        self::assertSame('2026-11-09T08:30:00+01:00', $service->updatedAt()->format(DATE_RFC3339));
    }

    public function testNamesDifferingOnlyInCaseOrSpacingCollide(): void
    {
        $this->database()->persisted(Structure::service(name: 'Sunday School'));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->database()->persisted(Structure::service(name: '  sunday   SCHOOL'));
    }

    public function testAnArchivedServiceKeepsItsName(): void
    {
        $archived = Structure::service(name: 'Sunday School');
        $archived->archive(Structure::now());
        $this->database()->persisted($archived);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->database()->persisted(Structure::service(name: 'sunday school'));
    }

    #[DataProvider('toneChecks')]
    public function testTheDatabaseKeepsTheToneAndNameInRange(string $set): void
    {
        $this->database()->persisted(Structure::service());

        $this->database()->assertRefused(
            OrganizationDatabase::CHECK_VIOLATION,
            'UPDATE organization.service SET ' . $set,
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function toneChecks(): iterable
    {
        yield 'tone 0' => ['tone = 0'];
        yield 'tone 9' => ['tone = 9'];
        yield 'blank name' => ["name = '   '"];
        yield 'description of 2001 characters' => ["description = repeat('a', 2001)"];
    }

    public function testAServiceWithAStageCannotBeDeleted(): void
    {
        $service = Structure::service();
        $this->database()->persisted($service, Structure::stage($service));

        $this->database()->assertRefused(OrganizationDatabase::RESTRICT_VIOLATION, 'DELETE FROM organization.service');
    }

    private function database(): OrganizationDatabase
    {
        return new OrganizationDatabase(self::getContainer());
    }
}
