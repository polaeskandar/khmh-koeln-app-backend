<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Console\Privacy;

use App\Library\Types\Model\Integers;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Domain\Entity\MemberLinks;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Database;
use App\Tests\Support\Interface\Api\Privacy\ErasureFixture;
use App\Tests\Support\JsonBody;
use App\Tests\Support\Logs;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PrivacyEraseDueCommandTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string NOTES = <<<'SQL'
        SELECT (SELECT count(cancel_reason) FROM scheduling.session)
             + (SELECT count(removal_reason) FROM scheduling.attendance)
             + (SELECT count(notes) FROM learning.lesson)
        SQL;

    private const string KIRO = "SELECT count(*) FROM people.person WHERE first_name = 'Kiro'";

    public function testAMembersAccountAndOwnRecordGoAfterFourteenDays(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $fixture = new ErasureFixture(self::getContainer());
        [$kiro] = $fixture->kiro();
        $account = new SignInAccounts(self::getContainer())->add('kiro@example.org');
        new MemberLinks(self::getContainer())->link($account, $kiro->id()->toRfc4122());
        $this->schedule('kiro@example.org');

        self::mockTime('2026-11-21T23:59:00+01:00');
        self::assertStringContainsString('Erased 0 scheduled requests, 0 failed.', $this->display());
        self::assertSame(1, $this->numberOf(self::KIRO));

        self::mockTime('2026-11-22T03:30:00+01:00');
        $tester = $this->tester();
        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('Erased 1 scheduled requests, 0 failed.', $tester->getDisplay());

        self::assertSame(0, $this->numberOf(self::KIRO));
        self::assertSame(0, $this->numberOf('SELECT count(*) FROM identity.app_user WHERE id = ?', [$account]));
        self::assertSame(1, $this->numberOf("SELECT count(*) FROM identity.app_user WHERE email = 'hoda@example.org'"));
        self::assertSame(2, $this->numberOf(self::NOTES));

        $request = new Database(self::getContainer())
            ->connection()
            ->fetchAssociative(
                'SELECT status, closed_at::text, closed_by, outcome::text, erased_subject_kind, erased_subject_group, app_user_id FROM privacy.data_request',
            );

        self::assertSame('done', $request['status'] ?? null);
        self::assertSame('2026-11-22 03:30:00+01', $request['closed_at'] ?? null);
        self::assertNull($request['closed_by'] ?? null);
        self::assertNull($request['app_user_id'] ?? null);
        self::assertSame('account', $request['erased_subject_kind'] ?? null);
        self::assertNull($request['erased_subject_group'] ?? null);

        $outcome = JsonBody::asArray(
            json_decode(
                Type::of($request['outcome'] ?? null)->toString(),
                associative: true,
                flags: JSON_THROW_ON_ERROR,
            ),
            'outcome',
        );

        self::assertSame(1, $outcome['person'] ?? null);
        self::assertSame(1, $outcome['account'] ?? null);
        self::assertSame(1, $outcome['guardianLinks'] ?? null);
        self::assertSame(3, $outcome['notes'] ?? null);

        self::assertStringContainsString('Erased 0 scheduled requests, 0 failed.', $this->display());
    }

    public function testAParentsChildrenKeepTheirRecords(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $families = new Families(self::getContainer());
        $child = $families->child('Mina');
        $parent = new SignInAccounts(self::getContainer())->addWithPerson('hoda@example.org');
        new RoleGrants(self::getContainer())->grant($parent, 'parent');
        $families->link($parent, $child);
        $this->schedule('hoda@example.org');

        self::mockTime('2026-11-25T08:00:00+01:00');
        self::assertStringContainsString('Erased 1 scheduled requests, 0 failed.', $this->display());

        self::assertSame(0, $this->numberOf('SELECT count(*) FROM identity.app_user'));
        self::assertSame(1, $this->numberOf("SELECT count(*) FROM people.person WHERE first_name = 'Mina'"));
        self::assertSame(0, $this->numberOf('SELECT count(*) FROM people.guardian_relationship'));

        self::assertSame(
            1,
            $this->numberOf(
                "SELECT count(*) FROM privacy.data_request WHERE status = 'done' AND erased_subject_kind = 'account' AND (outcome->>'guardianLinks')::int = 1",
            ),
        );
    }

    public function testACancelledErasureIsLeftAlone(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        new SignInAccounts(self::getContainer())->addWithPerson('hoda@example.org');
        $this->schedule('hoda@example.org');

        $this->jsonRequest('POST', '/api/auth/erasure-cancellation', [
            'username' => 'hoda',
            'password' => SignInAccounts::TYPED,
        ]);

        self::assertResponseStatusCodeSame(204);

        self::mockTime('2026-12-08T10:00:00+01:00');
        self::assertStringContainsString('Erased 0 scheduled requests, 0 failed.', $this->display());
        self::assertSame(1, $this->numberOf('SELECT count(*) FROM identity.app_user'));
        self::assertSame(1, $this->numberOf("SELECT count(*) FROM privacy.data_request WHERE status = 'withdrawn'"));
    }

    public function testAFailureIsLoggedAndTheOthersAreStillErased(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $accounts = new SignInAccounts(self::getContainer());
        $grants = new RoleGrants(self::getContainer());
        $leaving = $accounts->addWithPerson('admin@example.org');
        $other = $accounts->addWithPerson('mariam@example.org');
        $grants->grant($leaving, 'administrator');
        $grants->grant($other, 'administrator');
        $accounts->addWithPerson('hoda@example.org');
        $this->schedule('admin@example.org');
        $this->schedule('hoda@example.org');

        new Database(self::getContainer())
            ->connection()
            ->update('identity.app_user', ['disabled_at' => '2026-11-09 12:00:00+01'], ['id' => $other]);

        self::mockTime('2026-11-22T03:30:00+01:00');
        $tester = $this->tester();
        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('Erased 1 scheduled requests, 1 failed.', $tester->getDisplay());

        self::assertSame(1, $this->numberOf('SELECT count(*) FROM identity.app_user WHERE id = ?', [$leaving]));
        self::assertSame(0, $this->numberOf("SELECT count(*) FROM identity.app_user WHERE email = 'hoda@example.org'"));
        self::assertSame(1, $this->numberOf("SELECT count(*) FROM privacy.data_request WHERE status = 'open'"));

        $failure = new Logs(self::getContainer())->containing('A scheduled erasure failed.');
        self::assertCount(1, $failure);
        self::assertStringContainsString('requestId', $failure[0] ?? '');
        self::assertStringNotContainsString('admin@example.org', $failure[0] ?? '');
    }

    public function testADryRunChangesNothing(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        new SignInAccounts(self::getContainer())->addWithPerson('hoda@example.org');
        $this->schedule('hoda@example.org');

        self::mockTime('2026-11-22T03:30:00+01:00');
        $tester = $this->tester();
        self::assertSame(Command::SUCCESS, $tester->execute(['--dry-run' => true]));

        self::assertStringContainsString(
            'Dry run, nothing changed: Erased 1 scheduled requests, 0 failed.',
            $tester->getDisplay(),
        );

        self::assertSame(1, $this->numberOf('SELECT count(*) FROM identity.app_user'));
        self::assertSame(1, $this->numberOf("SELECT count(*) FROM privacy.data_request WHERE status = 'open'"));
    }

    private function schedule(string $email): void
    {
        $this->browser()->getCookieJar()->clear();
        $this->signInAs($email, SignInAccounts::TYPED);
        $this->jsonRequest('POST', '/api/me/erasure-request', ['password' => SignInAccounts::TYPED]);
        self::assertResponseStatusCodeSame(201);
        $this->browser()->getCookieJar()->clear();
    }

    private function display(): string
    {
        $tester = $this->tester();
        $tester->execute([]);

        return $tester->getDisplay();
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new Application($this->browser()->getKernel())->find('app:privacy:erase-due'));
    }

    /**
     * @param list<string> $params
     */
    private function numberOf(string $sql, array $params = []): int
    {
        return Integers::of(
            new Database(self::getContainer())
                ->connection()
                ->fetchOne($sql, $params),
        );
    }
}
