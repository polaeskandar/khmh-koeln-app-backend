<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\People\Domain\Entity;

use App\Context\People\Domain\Entity\Person;
use App\Context\People\Infrastructure\Persistence\Repository\DoctrinePersonRepository;
use App\Tests\Support\Database;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PersonTest extends KernelTestCase
{
    public function testAPersonWithoutBirthDateOrAddressIsStored(): void
    {
        $person = new Person('Mina', 'Hanna', 'Mini');

        new DoctrinePersonRepository($this->database()->entityManager())->save($person);

        self::assertSame(
            ['name_key' => 'mina_hanna', 'search_text' => 'mina hanna mini', 'birth_date' => null, 'street' => null],
            $this
                ->database()
                ->connection()
                ->fetchAssociative('SELECT name_key, search_text, birth_date, street FROM people.person'),
        );
    }

    /**
     * @param array<string, string> $set
     */
    #[DataProvider('violations')]
    public function testTheDatabaseRefusesAnInvalidPerson(array $set): void
    {
        $person = new Person('Mina', 'Hanna', null);
        $this->database()->persisted($person);

        $this->database()->assertRefused(Database::CHECK_VIOLATION, static fn (Connection $connection): int|string => $connection->update(
            'people.person',
            $set,
            ['id' => $person->id()->toRfc4122()],
        ));
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function violations(): iterable
    {
        yield 'unknown status' => [['status' => 'gone']];
        yield 'blank first name' => [['first_name' => ' ']];
        yield 'blank last name' => [['last_name' => '']];
        yield 'street only' => [['street' => 'Domstraße 1']];
        yield 'no city' => [['street' => 'Domstraße 1', 'postal_code' => '50667']];
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
