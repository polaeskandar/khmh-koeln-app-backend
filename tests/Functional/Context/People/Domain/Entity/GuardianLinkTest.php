<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\People\Domain\Entity;

use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GuardianLinkTest extends KernelTestCase
{
    public function testAnAccountHasOneCurrentLinkPerChild(): void
    {
        $families = new Families(self::getContainer());
        $account = $families->account('mariam@example.org');
        $child = $families->child();
        $families->link($account, $child, 'revoked');
        $families->link($account, $child);

        $this->database()->assertRefused(Database::UNIQUE_VIOLATION, static fn () => $families->link($account, $child));
    }

    #[DataProvider('violations')]
    public function testEachCheckRefusesItsRow(string $set): void
    {
        $families = new Families(self::getContainer());
        $families->link($families->account('mariam@example.org'), $families->child());

        $this->database()->assertRefused(Database::CHECK_VIOLATION, 'UPDATE people.guardian_relationship SET ' . $set);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function violations(): iterable
    {
        yield 'unknown relationship' => ["relationship = 'uncle'"];
        yield 'unknown status' => ["status = 'pending'"];
        yield 'revoked without a time' => ["status = 'revoked'"];
        yield 'a time without revoking' => ['revoked_at = approved_at'];
    }

    public function testDeletingTheChildDeletesItsLinks(): void
    {
        $families = new Families(self::getContainer());
        $child = $families->child();
        $families->link($families->account('mariam@example.org'), $child);

        $this->database()->connection()->executeStatement('DELETE FROM people.person');

        self::assertSame(
            0,
            $this->database()->connection()->fetchOne('SELECT count(*) FROM people.guardian_relationship'),
        );
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
