<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\People\Domain\Entity;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Library\Time\Model\LocalDate;
use App\Tests\Support\Database;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class MembershipTest extends KernelTestCase
{
    private const string SERVICE = '019a3b6e-0000-7000-8000-000000000001';

    private const string STAGE = '019a3b6e-0000-7000-8000-000000000002';

    private const string OTHER_STAGE = '019a3b6e-0000-7000-8000-000000000003';

    public function testAMembershipCannotEndBeforeItStarts(): void
    {
        $person = $this->person();

        $this->database()->assertRefused(Database::CHECK_VIOLATION, static fn (Connection $connection): int|string => $connection->insert('people.membership', self::row($person, [
            'starts_on' => '2026-11-08',
            'ends_on' => '2026-11-07',
        ])));
    }

    /**
     * @param array<string, string|null> $columns
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('rosters')]
    public function testAPersonIsOnARosterOnceAtATime(array $columns): void
    {
        $person = $this->person();
        $this->database()->connection()->insert('people.membership', self::row($person, $columns));

        $this->database()->assertRefused(Database::UNIQUE_VIOLATION, static fn (Connection $connection): int|string => $connection->insert('people.membership', self::row(
            $person,
            $columns,
        )));
    }

    /**
     * @return iterable<string, array{array<string, string|null>}>
     */
    public static function rosters(): iterable
    {
        yield 'stage' => [['stage_id' => self::STAGE]];
        yield 'service-level roster' => [['stage_id' => null]];
    }

    public function testEndedAndOpenMembershipsAndTwoStagesCoexist(): void
    {
        $person = $this->person();
        $connection = $this->database()->connection();

        $connection->insert('people.membership', self::row($person, [
            'stage_id' => self::STAGE,
            'ends_on' => '2026-10-31',
        ]));

        $connection->insert('people.membership', self::row($person, ['stage_id' => self::STAGE]));
        $connection->insert('people.membership', self::row($person, ['stage_id' => self::OTHER_STAGE]));
        $connection->insert('people.membership', self::row($person, ['stage_id' => null]));

        self::assertSame(4, $connection->fetchOne('SELECT count(*) FROM people.membership'));
    }

    public function testAPersonWithAMembershipCannotBeDeleted(): void
    {
        $person = new Person('Mina', 'Hanna', null);

        new Database(self::getContainer())->persisted(
            $person,
            new Membership(
                $person,
                Uuid::fromString(self::SERVICE),
                stageId: null,
                startsOn: LocalDate::of('2026-09-01'),
            ),
        );

        $this->database()->assertRefused(Database::RESTRICT_VIOLATION, 'DELETE FROM people.person');
    }

    private function person(): string
    {
        $person = new Person('Mina', 'Hanna', null);
        $this->database()->persisted($person);

        return $person->id()->toRfc4122();
    }

    /**
     * @param array<string, string|null> $columns
     *
     * @return array<string, string|null>
     */
    private static function row(string $person, array $columns): array
    {
        return [
            'id' => Uuid::v7()->toRfc4122(),
            'person_id' => $person,
            'service_id' => self::SERVICE,
            'starts_on' => '2026-09-01',
            ...$columns,
        ];
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
