<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Organization\Domain\Entity;

use App\Tests\Support\Context\Organization\Domain\Entity\OrganizationDatabase;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class StageTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    public function testTheClockStampsCreation(): void
    {
        self::mockTime('2026-11-08 12:00:00+01:00');
        $stage = Structure::stage(Structure::service());
        $this->database()->persisted($stage->service(), $stage);

        self::assertSame('2026-11-08T12:00:00+01:00', $stage->createdAt()->format(DATE_RFC3339));
    }

    public function testAStageNameMayRepeatAcrossServices(): void
    {
        $sundaySchool = Structure::service(name: 'Sunday School');
        $youth = Structure::service(name: 'Youth Meeting');

        $this->database()->persisted(
            $sundaySchool,
            $youth,
            Structure::stage($sundaySchool, name: 'Beginners'),
            Structure::stage($youth, name: 'beginners'),
        );

        self::assertCount(
            2,
            $this->database()->entityManager()->getRepository(Structure::stage($youth)::class)->findAll(),
        );
    }

    public function testAStageNameIsUniqueWithinItsServiceArchivedOrNot(): void
    {
        $service = Structure::service();
        $archived = Structure::stage($service, name: 'Beginners');
        $archived->archive(Structure::now());
        $this->database()->persisted($service, $archived);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->database()->persisted(Structure::stage($service, name: ' BEGINNERS ', position: 1));
    }

    #[DataProvider('stageChecks')]
    public function testTheDatabaseKeepsThePositionAndNameInRange(string $set): void
    {
        $stage = Structure::stage(Structure::service());
        $this->database()->persisted($stage->service(), $stage);

        $this->database()->assertRefused(
            OrganizationDatabase::CHECK_VIOLATION,
            'UPDATE organization.stage SET ' . $set,
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function stageChecks(): iterable
    {
        yield 'position -1' => ['position = -1'];
        yield 'blank name' => ["name = ''"];
        yield 'description of 501 characters' => ["description = repeat('a', 501)"];
    }

    private function database(): OrganizationDatabase
    {
        return new OrganizationDatabase(self::getContainer());
    }
}
