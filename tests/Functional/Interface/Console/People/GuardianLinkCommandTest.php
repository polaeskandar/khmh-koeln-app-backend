<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Console\People;

use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class GuardianLinkCommandTest extends KernelTestCase
{
    public function testLinkingGivesOneLinkAndOneParentRoleEvenWhenRepeated(): void
    {
        $families = new Families(self::getContainer());
        $families->account('mariam@example.org');
        $child = $families->child();

        foreach ([1, 2] as $run) {
            self::assertSame(
                Command::SUCCESS,
                $this->tester()->execute([
                    'username' => 'Mariam',
                    'personId' => $child->id()->toRfc4122(),
                ]),
                (string) $run,
            );
        }

        self::assertSame(1, $this->rowsIn('people.guardian_relationship'));
        self::assertSame(1, $this->rowsIn('access.user_role'));
    }

    /**
     * @param array<string, string|null> $columns
     */
    #[DataProvider('refusals')]
    public function testARefusalWritesNothing(
        array $columns,
        string $personStatus,
        ?string $personId,
        string $code,
    ): void {
        $families = new Families(self::getContainer());
        $families->account('mariam@example.org', $columns);
        $child = $families->child();
        $this->database()->connection()->executeStatement('UPDATE people.person SET status = ?', [$personStatus]);
        $tester = $this->tester();

        $status = $tester->execute([
            'username' => 'mariam',
            'personId' => $personId ?? $child->id()->toRfc4122(),
        ]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString($code, $tester->getDisplay());
        self::assertSame(0, $this->rowsIn('people.guardian_relationship'));
        self::assertSame(0, $this->rowsIn('access.user_role'));
    }

    /**
     * @return iterable<string, array{array<string, string|null>, string, ?string, string}>
     */
    public static function refusals(): iterable
    {
        yield 'disabled account' => [
            ['disabled_at' => '2026-11-09 12:00:00+01'],
            'active',
            null,
            'guardians.accountInactive',
        ];

        yield 'inactive child' => [[], 'inactive', null, 'guardians.childInactive'];
        yield 'unknown child' => [[], 'active', '019a3b6e-0000-7000-8000-000000000009', 'notFound.person'];
    }

    public function testAnUnknownRelationshipNamesItsCode(): void
    {
        $families = new Families(self::getContainer());
        $families->account('mariam@example.org', []);
        $child = $families->child();
        $tester = $this->tester();

        $status = $tester->execute([
            'username' => 'mariam',
            'personId' => $child->id()->toRfc4122(),
            '--relationship' => 'uncle',
        ]);

        self::assertSame(Command::INVALID, $status);
        self::assertStringContainsString('relationship: validation.invalidChoice', $tester->getDisplay());
        self::assertSame(0, $this->rowsIn('people.guardian_relationship'));
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new Application(self::bootKernel())->find('app:guardian:link'));
    }

    private function rowsIn(string $table): int
    {
        $count = $this
            ->database()
            ->connection()
            ->fetchOne('SELECT count(*) FROM ' . $table);

        self::assertIsInt($count);

        return $count;
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
