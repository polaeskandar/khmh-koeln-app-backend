<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\People\Infrastructure\Persistence\Migrations;

use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\ValueObject\PersonName;
use App\Context\People\Infrastructure\Persistence\Migrations\Version20261110045223;
use App\Tests\Support\Database;
use App\Tests\Support\Library\Strings\Model\KeysBeforeComposition;
use App\Tests\Support\MigrationUp;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class Version20261110045223Test extends KernelTestCase
{
    public function testDecomposedNamesAndKeysAreComposedAndNamesakesStay(): void
    {
        $composed = new Person(PersonName::of('Jürgen', 'Müller', 'Jü'));
        $decomposed = new Person(PersonName::of('Mina', 'Hanna', null));
        $this->database()->persisted($composed, $decomposed);
        $this->storeAsTypedBefore($decomposed->id(), "Ju\u{0308}rgen", "Mu\u{0308}ller", "Ju\u{0308}");

        $connection = $this->database()->connection();
        new MigrationUp($connection)->run(new Version20261110045223($connection, new NullLogger()));

        self::assertSame(
            [
                'first_name' => "J\u{00FC}rgen",
                'last_name' => "M\u{00FC}ller",
                'display_name' => "J\u{00FC}",
                'name_key' => "j\u{00FC}rgen_m\u{00FC}ller",
                'search_text' => 'jurgen muller ju',
            ],
            $this->namesOf($decomposed->id()),
        );
        self::assertSame($this->namesOf($composed->id()), $this->namesOf($decomposed->id()));
    }

    private function storeAsTypedBefore(Uuid $id, string $firstName, string $lastName, string $displayName): void
    {
        $this
            ->database()
            ->connection()
            ->executeStatement(
                <<<'SQL'
                    UPDATE people.person
                    SET first_name = ?, last_name = ?, display_name = ?, name_key = ?, search_text = ?
                    WHERE id = ?
                    SQL,
                [
                    $firstName,
                    $lastName,
                    $displayName,
                    KeysBeforeComposition::of($firstName . ' ' . $lastName),
                    'jurgen muller ju',
                    $id->toRfc4122(),
                ],
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function namesOf(Uuid $id): array
    {
        $row = $this
            ->database()
            ->connection()
            ->fetchAssociative('SELECT first_name, last_name, display_name, name_key, search_text FROM people.person WHERE id = ?', [$id->toRfc4122()]);

        self::assertIsArray($row);

        return $row;
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
