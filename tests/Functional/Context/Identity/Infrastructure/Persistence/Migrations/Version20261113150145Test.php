<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Identity\Infrastructure\Persistence\Migrations;

use App\Context\Identity\Infrastructure\Persistence\Migrations\Version20261113150145;
use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Database;
use App\Tests\Support\MigrationDown;
use App\Tests\Support\MigrationUp;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class Version20261113150145Test extends KernelTestCase
{
    private const string DETAILS = 'SELECT birth_date::text, street, postal_code, city FROM people.person WHERE id = ?';

    public function testThePersonGetsTheDetailsOnlyTheAccountHeld(): void
    {
        $connection = new Database(self::getContainer())->connection();
        new MigrationDown($connection)->run(new Version20261113150145($connection, new NullLogger()));

        $bare = $this->account('mina@example.org', [
            'birth_date' => '2001-02-03',
            'street' => 'Domstraße 1',
            'postal_code' => '50667',
            'city' => 'Köln',
        ]);

        $own = $this->account('hoda@example.org', [
            'birth_date' => '1980-05-06',
            'street' => 'Ring 2',
            'postal_code' => '50670',
            'city' => 'Köln',
        ]);
        $connection->update(
            'people.person',
            [
                'birth_date' => '1981-01-01',
                'street' => 'Weg 3',
                'postal_code' => '50668',
                'city' => 'Köln',
            ],
            ['id' => $own],
        );

        $nothing = $this->account('kiro@example.org', []);

        new MigrationUp($connection)->run(new Version20261113150145($connection, new NullLogger()));

        self::assertSame(
            ['birth_date' => '2001-02-03', 'street' => 'Domstraße 1', 'postal_code' => '50667', 'city' => 'Köln'],
            $connection->fetchAssociative(self::DETAILS, [$bare]),
        );

        self::assertSame(
            ['birth_date' => '1981-01-01', 'street' => 'Weg 3', 'postal_code' => '50668', 'city' => 'Köln'],
            $connection->fetchAssociative(self::DETAILS, [$own]),
        );

        self::assertSame(
            ['birth_date' => null, 'street' => null, 'postal_code' => null, 'city' => null],
            $connection->fetchAssociative(self::DETAILS, [$nothing]),
        );
    }

    /**
     * @param array<string, string> $details the account's own birth date and address columns
     *
     * @return string the id of the account's person
     */
    private function account(string $email, array $details): string
    {
        $connection = new Database(self::getContainer())->connection();
        $person = Accounts::person($connection, 'Mina Hanna');

        $connection->insert('identity.app_user', [
            ...Accounts::row(['email' => $email, 'person_id' => $person]),
            'email' => $email,
            'display_name' => 'Mina Hanna',
            ...$details,
        ]);

        return $person;
    }
}
