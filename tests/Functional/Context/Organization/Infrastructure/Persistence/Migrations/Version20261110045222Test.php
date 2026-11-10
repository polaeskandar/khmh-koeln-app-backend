<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Organization\Infrastructure\Persistence\Migrations;

use App\Context\Organization\Infrastructure\Persistence\Migrations\Version20261110045222;
use App\Library\Strings\Model\NameKey;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use App\Tests\Support\Library\Strings\Model\KeysBeforeComposition;
use App\Tests\Support\MigrationUp;
use Doctrine\DBAL\Exception\DriverException;
use IntlChar;
use Normalizer;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class Version20261110045222Test extends KernelTestCase
{
    public function testComposingAKeyStoredBeforeGivesTheKeyOfTheComposedName(): void
    {
        $names = array_values(array_unique(iterator_to_array(self::everyDecomposableName(), preserve_keys: false)));

        $composed = $this
            ->database()
            ->connection()
            ->fetchFirstColumn(
                <<<'SQL'
                    SELECT normalize(stored.key, NFC)
                    FROM unnest(string_to_array(?, chr(10))) WITH ORDINALITY AS stored(key, position)
                    ORDER BY stored.position
                    SQL,
                [implode("\n", array_map(KeysBeforeComposition::of(...), $names))],
            );

        self::assertSame(
            array_combine(array_map(bin2hex(...), $names), array_map(NameKey::of(...), $names)),
            array_combine(array_map(bin2hex(...), $names), $composed),
        );
    }

    public function testDecomposedNamesAndTheirKeysAreComposed(): void
    {
        $choir = Structure::service('Choir');
        $youth = Structure::service('Youth');
        $decomposed = Structure::stage($choir, 'Older');
        $composed = Structure::stage($youth, "\u{00C4}ltere");
        $this->database()->persisted($choir, $youth, $decomposed, $composed);
        $this->storeAsTypedBefore('organization.service', $choir->id(), "Chor Mu\u{0308}hle");
        $this->storeAsTypedBefore('organization.stage', $decomposed->id(), "A\u{0308}ltere");

        $this->migrate();

        self::assertSame(
            ['name' => "Chor M\u{00FC}hle", 'name_key' => "chor_m\u{00FC}hle"],
            $this->nameIn('organization.service', $choir->id()),
        );
        self::assertSame(
            ['name' => "\u{00C4}ltere", 'name_key' => "\u{00E4}ltere"],
            $this->nameIn('organization.stage', $decomposed->id()),
        );
        self::assertSame(
            $this->nameIn('organization.stage', $composed->id()),
            $this->nameIn('organization.stage', $decomposed->id()),
        );
    }

    public function testNamesThatWouldShareAKeyStopTheMigrationAndAreListed(): void
    {
        $composed = Structure::service("Chor M\u{00FC}hle");
        $decomposed = Structure::service('Choir');
        $stage = Structure::stage($composed, "\u{00C4}ltere");
        $stageTwin = Structure::stage($composed, 'Older', position: 1);
        $this->database()->persisted($composed, $decomposed, $stage, $stageTwin);
        $this->storeAsTypedBefore('organization.service', $decomposed->id(), "Chor Mu\u{0308}hle");
        $this->storeAsTypedBefore('organization.stage', $stageTwin->id(), "A\u{0308}ltere");

        $message = $this->refusal();

        self::assertStringContainsString('these names would share one key', $message);
        self::assertStringContainsString(sprintf("service %s 'Chor M", $composed->id()->toRfc4122()), $message);
        self::assertStringContainsString(sprintf("service %s 'Chor M", $decomposed->id()->toRfc4122()), $message);
        self::assertStringContainsString(sprintf('stage %s ', $stage->id()->toRfc4122()), $message);
        self::assertStringContainsString(sprintf('stage %s ', $stageTwin->id()->toRfc4122()), $message);
        self::assertSame(
            ['name' => "Chor Mu\u{0308}hle", 'name_key' => "chor_mu\u{0308}hle"],
            $this->nameIn('organization.service', $decomposed->id()),
        );
    }

    /**
     * @return iterable<string>
     */
    private static function everyDecomposableName(): iterable
    {
        for ($codePoint = 0; $codePoint <= 0x10_FFFF; ++$codePoint) {
            if (IntlChar::charType($codePoint) === IntlChar::CHAR_CATEGORY_SURROGATE) {
                continue;
            }

            $character = (string) IntlChar::chr($codePoint);

            if (IntlChar::getCombiningClass($codePoint) !== 0) {
                yield $character;
                yield 'I' . $character;
                yield 'J' . $character;
            }

            if (Normalizer::isNormalized($character, Normalizer::FORM_D)) {
                continue;
            }

            $decomposed = (string) Normalizer::normalize($character, Normalizer::FORM_D);

            yield $character;
            yield $decomposed;
            yield mb_strtoupper($character);
            yield mb_strtoupper($decomposed);
            yield (string) Normalizer::normalize(mb_strtoupper($character), Normalizer::FORM_D);
        }
    }

    private function storeAsTypedBefore(string $table, Uuid $id, string $name): void
    {
        $this
            ->database()
            ->connection()
            ->executeStatement(sprintf('UPDATE %s SET name = ?, name_key = ? WHERE id = ?', $table), [
                $name,
                KeysBeforeComposition::of($name),
                $id->toRfc4122(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function nameIn(string $table, Uuid $id): array
    {
        $row = $this
            ->database()
            ->connection()
            ->fetchAssociative(sprintf('SELECT name, name_key FROM %s WHERE id = ?', $table), [$id->toRfc4122()]);

        self::assertIsArray($row);

        return $row;
    }

    private function refusal(): string
    {
        try {
            $this
                ->database()
                ->connection()
                ->transactional($this->migrate(...));
        } catch (DriverException $refusal) {
            self::assertSame('P0001', $refusal->getSQLState());

            return $refusal->getMessage();
        }

        self::fail('The migration composed names that share a key.');
    }

    private function migrate(): void
    {
        $connection = $this->database()->connection();

        new MigrationUp($connection)->run(new Version20261110045222($connection, new NullLogger()));
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
