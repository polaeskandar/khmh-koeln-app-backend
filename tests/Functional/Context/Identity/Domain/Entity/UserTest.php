<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Identity\Domain\Entity;

use App\Context\Identity\Infrastructure\Persistence\Repository\DoctrineUserRepository;
use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Database;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class UserTest extends KernelTestCase
{
    public function testAnEmailBelongsToOneAccount(): void
    {
        $this->database()->connection()->insert('identity.app_user', Accounts::row());

        $this->database()->assertRefused(Database::UNIQUE_VIOLATION, static fn (Connection $connection): int|string => $connection->insert(
            'identity.app_user',
            Accounts::row(),
        ));
    }

    /**
     * @param array<string, string|null> $columns
     */
    #[DataProvider('violations')]
    public function testTheDatabaseRefusesAnInvalidAccount(array $columns): void
    {
        $this->database()->assertRefused(Database::CHECK_VIOLATION, static fn (Connection $connection): int|string => $connection->insert(
            'identity.app_user',
            Accounts::row($columns),
        ));
    }

    /**
     * @return iterable<string, array{array<string, string|null>}>
     */
    public static function violations(): iterable
    {
        yield 'email in mixed case' => [['email' => 'Sara@example.org']];
        yield 'email with spaces' => [['email' => ' sara@example.org']];
        yield 'blank display name' => [['display_name' => '  ']];
        yield 'unknown language' => [['language' => 'fr']];
        yield 'street only' => [['street' => 'Domstraße 1']];
        yield 'no city' => [['street' => 'Domstraße 1', 'postal_code' => '50667']];
        yield 'unknown age check' => [['person_id' => Uuid::v7()->toRfc4122(), 'age_check' => 'adult']];

        yield 'note without an age check' => [['guardian_consent_note' => 'Mother agreed.']];

        yield 'note without a consent' => [[
            'person_id' => Uuid::v7()->toRfc4122(),
            'age_check' => 'sixteenOrOlder',
            'guardian_consent_note' => 'Mother agreed.',
        ]];
    }

    public function testAMemberRecordBelongsToOneAccount(): void
    {
        $personId = Uuid::v7()->toRfc4122();
        $connection = $this->database()->connection();

        $connection->insert('identity.app_user', Accounts::row([
            'person_id' => $personId,
            'age_check' => 'sixteenOrOlder',
        ]));

        $this->database()->assertRefused(Database::UNIQUE_VIOLATION, static fn (Connection $other): int|string => $other->insert('identity.app_user', Accounts::row([
            'email' => 'nevine@example.org',
            'person_id' => $personId,
            'age_check' => 'guardianConsent',
        ])));

        self::assertSame(1, $connection->fetchOne('SELECT count(*) FROM identity.app_user'));
    }

    public function testAnAccountWithAFullAddressIsAccepted(): void
    {
        $this
            ->database()
            ->connection()
            ->insert('identity.app_user', Accounts::row([
                'street' => 'Domstraße 1',
                'postal_code' => '50667',
                'city' => 'Köln',
            ]));

        self::assertSame(1, $this->database()->connection()->fetchOne('SELECT count(*) FROM identity.app_user'));
    }

    /**
     * @param array<string, string|null> $columns
     */
    #[DataProvider('inactiveAccounts')]
    public function testOnlyAnAccountThatIsNotDisabledIsActive(array $columns, bool $active): void
    {
        $this->database()->connection()->insert('identity.app_user', Accounts::row($columns));

        $user = new DoctrineUserRepository($this->database()->entityManager())->findByEmail('sara@example.org');

        self::assertSame($active, $user?->status()->isActive());
    }

    /**
     * @return iterable<string, array{array<string, string|null>, bool}>
     */
    public static function inactiveAccounts(): iterable
    {
        yield 'enabled' => [[], true];
        yield 'disabled' => [['disabled_at' => '2026-11-09 08:00:00+01'], false];
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
