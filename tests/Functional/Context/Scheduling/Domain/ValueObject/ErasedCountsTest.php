<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Scheduling\Domain\ValueObject;

use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\Scheduling\Domain\Entity\Schedules;
use App\Tests\Support\Database;
use Doctrine\DBAL\Exception\DriverException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ErasedCountsTest extends KernelTestCase
{
    #[DataProvider('impossibleCounters')]
    public function testTheDatabaseRefusesImpossibleCounters(string $set): void
    {
        $service = Structure::service();
        new Database(self::getContainer())->persisted($service);
        $schedules = new Schedules(self::getContainer());
        $session = $schedules->session($schedules->weekly($service, 'Service', 7), '2026-11-08');
        $sqlState = null;

        try {
            new Database(self::getContainer())
                ->connection()
                ->executeStatement('UPDATE scheduling.session SET '
                . $set
                . ' WHERE id = ?', [$session->id()->toRfc4122()]);
        } catch (DriverException $refused) {
            $sqlState = $refused->getSQLState();
        }

        self::assertSame('23514', $sqlState);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function impossibleCounters(): iterable
    {
        yield 'negative' => ['erased_present = -1'];
        yield 'more on the roster and present than on the roster' => [
            'erased_roster = 1, erased_present = 3, erased_roster_present = 2',
        ];

        yield 'more on the roster and present than present' => [
            'erased_roster = 3, erased_present = 1, erased_roster_present = 2',
        ];
    }
}
