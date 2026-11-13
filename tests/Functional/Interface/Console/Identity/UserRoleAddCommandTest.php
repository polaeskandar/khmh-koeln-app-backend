<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Console\Identity;

use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

final class UserRoleAddCommandTest extends KernelTestCase
{
    public function testTheRoleIsGivenByUsernameAndNameInAnyCase(): void
    {
        $this->account('daniel@example.org');
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['username' => 'Daniel', 'role' => 'servant']));

        self::assertSame(['servant'], $this->roleKeys());
        self::assertStringContainsString('Gave Daniel the role servant.', $tester->getDisplay());
    }

    /**
     * @param array<string, string|null> $columns
     */
    #[DataProvider('refusals')]
    public function testARefusalExitsWithItsCodeAndChangesNothing(
        string $email,
        string $role,
        array $columns,
        string $code,
    ): void {
        $this->account('daniel@example.org', $columns);
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute([
            'username' => Accounts::usernameOf($email),
            'role' => $role,
        ]));

        self::assertStringContainsString($code, $tester->getDisplay());
        self::assertSame([], $this->roleKeys());
    }

    /**
     * @return iterable<string, array{string, string, array<string, string|null>, string}>
     */
    public static function refusals(): iterable
    {
        yield 'unknown username' => ['nobody@example.org', 'Servant', [], 'notFound.account'];
        yield 'unknown role' => ['daniel@example.org', 'Deacon', [], 'notFound.role'];
    }

    /**
     * @param array<string, string|null> $columns
     */
    private function account(string $email, array $columns = []): void
    {
        new Database(self::getContainer())
            ->connection()
            ->insert('identity.app_user', Accounts::row([
                'id' => Uuid::v7()->toRfc4122(),
                'email' => $email,
                ...$columns,
            ]));
    }

    /**
     * @return list<string>
     */
    private function roleKeys(): array
    {
        $keys = new Database(self::getContainer())
            ->connection()
            ->fetchFirstColumn(
                'SELECT r.name_key FROM identity.user_role ur JOIN identity.role r ON r.id = ur.role_id',
            );

        return array_map(strval(...), $keys);
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new Application(self::bootKernel())->find('app:user:role:add'));
    }
}
