<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\People\Infrastructure\Security;

use App\Context\Identity\Domain\Repository\AccountRepositoryInterface;
use App\Context\Identity\Infrastructure\Security\SecurityUser;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Infrastructure\Security\GuardianVoter;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Uid\Uuid;

final class GuardianVoterTest extends KernelTestCase
{
    /**
     * @param array<string, string|null> $columns
     * @param list<string> $roles
     */
    #[DataProvider('accounts')]
    public function testOnlyAnAccountWithAnApprovedLinkActsForTheChild(
        array $columns,
        array $roles,
        ?string $link,
        bool $granted,
    ): void {
        $families = new Families(self::getContainer());
        $account = $families->account('mariam@example.org', $columns, ...$roles);
        $child = $families->child();

        if ($link !== null) {
            $families->link($account, $child, $link);
        }

        self::assertSame($granted, $this->actsFor($account, $child));
    }

    /**
     * @return iterable<string, array{array<string, string|null>, list<string>, ?string, bool}>
     */
    public static function accounts(): iterable
    {
        yield 'approved link' => [[], ['parent'], 'approved', true];
        yield 'a staff member as parent' => [[], ['servant'], 'approved', true];
        yield 'revoked link' => [[], ['parent'], 'revoked', false];
        yield 'administrator without a link' => [[], ['administrator'], null, false];
    }

    public function testAnotherFamilysChildIsDeniedAndARevokeCountsAtOnce(): void
    {
        $families = new Families(self::getContainer());
        $account = $families->account('mariam@example.org', [], 'parent');
        $own = $families->child();
        $other = $families->child('Mina');
        $families->link($account, $own);
        $families->link($families->account('sara@example.org', [], 'parent'), $other);

        self::assertFalse($this->actsFor($account, $other));
        self::assertTrue($this->actsFor($account, $own));

        new Database(self::getContainer())
            ->connection()
            ->executeStatement("UPDATE people.guardian_relationship SET status = 'revoked', revoked_at = now() WHERE user_id = ?", [
                $account,
            ]);

        new Database(self::getContainer())->entityManager()->clear();

        self::assertFalse($this->actsFor($account, $own));
    }

    private function actsFor(string $accountId, Person $child): bool
    {
        $accounts = self::getContainer()->get(AccountRepositoryInterface::class);
        self::assertInstanceOf(AccountRepositoryInterface::class, $accounts);
        $account = $accounts->find(Uuid::fromString($accountId));
        self::assertNotNull($account);

        $decisions = self::getContainer()->get('security.access.decision_manager');
        self::assertInstanceOf(AccessDecisionManagerInterface::class, $decisions);

        return $decisions->decide(
            new UsernamePasswordToken(SecurityUser::of($account), 'api', ['ROLE_USER']),
            [GuardianVoter::ACT_FOR_CHILD],
            $child,
        );
    }
}
