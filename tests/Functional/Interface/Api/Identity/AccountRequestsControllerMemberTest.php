<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Identity;

use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Database;
use App\Tests\Support\Interface\Api\Identity\MemberFixture;
use App\Tests\Support\Interface\Api\Identity\Registrations;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class AccountRequestsControllerMemberTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testAMemberRegistersWithTheGroupsTheyTakePartIn(): void
    {
        $this->jsonRequest('POST', '/api/auth/register', $this->registration('mina.hanna@example.org', '  '));
        $this->assertFieldError('registration.note', 'accounts.groupsRequired');

        $this->jsonRequest('POST', '/api/auth/register', $this->registration('mina.hanna@example.org'));
        self::assertResponseStatusCodeSame(202);

        self::assertSame(
            ['member', 'Youth Meeting, Deacons’ School Advanced', false],
            array_values($this->row(
                'mina.hanna@example.org',
                'registered_as, registration_note, approved_at IS NOT NULL',
            )),
        );
    }

    public function testApprovingLinksTheYouthsOwnRecordWithTheParentsAgreement(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        ['youth' => $youth] = new MemberFixture(self::getContainer())->people();
        $mina = $this->registered('mina.hanna@example.org');
        $this->signInWithRoles('mariam@example.org', 'administrator');

        $this->approve($mina, [
            'personId' => $youth,
            'ageCheck' => 'guardianConsent',
            'guardianConsentNote' => ' Father agreed. ',
        ]);

        self::assertResponseIsSuccessful();

        self::assertSame(
            [$youth, 'guardianConsent', 'Father agreed.', null, null],
            array_values($this->row(
                'mina.hanna@example.org',
                'person_id::text, age_check, guardian_consent_note, registered_as, registration_note',
            )),
        );

        $this->browser()->getCookieJar()->clear();
        $this->signInAs('mina.hanna@example.org', SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/me');
        $this->assertJsonPath('member.personId', $youth);
        $this->assertJsonPath('roles', []);
    }

    public function testRefusedApprovalsLeaveTheAccountPending(): void
    {
        ['child' => $child, 'nevine' => $nevine] = new MemberFixture(self::getContainer())->people();
        $mina = $this->registered('mina.hanna@example.org');

        new Database(self::getContainer())
            ->connection()
            ->update('people.person', ['status' => 'inactive'], ['id' => $child]);

        $this->signInWithRoles('mariam@example.org', 'administrator');

        $this->approve($mina, []);
        self::assertResponseStatusCodeSame(422);

        $this->assertJsonPath('fieldErrors', [
            'member.personId' => ['code' => 'accounts.choosePerson', 'params' => []],
            'member.ageCheck' => ['code' => 'accounts.chooseAge', 'params' => []],
        ]);

        $this->approve($mina, ['personId' => $child, 'ageCheck' => 'sixteenOrOlder']);
        $this->assertFieldError('member.personId', 'accounts.personNotFound');
        $this->approve($mina, ['personId' => $nevine, 'ageCheck' => 'sixteenOrOlder']);
        $this->assertFieldError('member.personId', 'accounts.personHasAccount', ['name' => 'Nevine Boulos']);

        $this->approve($mina, [
            'personId' => $nevine,
            'ageCheck' => 'guardianConsent',
            'guardianConsentNote' => str_repeat('a', times: 201),
        ]);

        $this->assertFieldError('member.guardianConsentNote', 'validation.tooLong', ['max' => 200, 'limit' => 200]);

        self::assertSame([false], array_values($this->row('mina.hanna@example.org', 'approved_at IS NOT NULL')));
    }

    public function testTwoAccountsRacingForOneRecordGiveOneLinkAndNever500(): void
    {
        ['youth' => $youth] = new MemberFixture(self::getContainer())->people();
        $first = $this->registered('mina.hanna@example.org');
        $second = $this->registered('mina.h@example.org');
        $this->signInWithRoles('mariam@example.org', 'administrator');

        $this->approve($first, ['personId' => $youth, 'ageCheck' => 'sixteenOrOlder']);
        self::assertResponseIsSuccessful();
        $this->approve($first, ['personId' => $youth, 'ageCheck' => 'sixteenOrOlder']);
        $this->assertProblem(409, 'accounts.alreadyApproved');
        $this->approve($second, ['personId' => $youth, 'ageCheck' => 'sixteenOrOlder']);
        $this->assertFieldError('member.personId', 'accounts.personHasAccount', ['name' => 'Mina Hanna']);

        $connection = new Database(self::getContainer())->connection();

        $connection->update(
            'identity.app_user',
            ['person_id' => null, 'age_check' => null],
            ['email' => 'mina.hanna@example.org'],
        );

        $this->racingLink('mina.hanna@example.org');
        $this->approve($second, ['personId' => $youth, 'ageCheck' => 'sixteenOrOlder']);

        $this->assertFieldError('member.personId', 'accounts.personHasAccount', ['name' => 'Mina Hanna']);
        self::assertSame([false], array_values($this->row('mina.h@example.org', 'approved_at IS NOT NULL')));
    }

    /**
     * @param array<string, string> $member
     */
    private function approve(string $accountId, array $member): void
    {
        $this->jsonRequest('POST', '/api/account-requests/' . $accountId . '/approve', [
            'as' => 'member',
            'member' => $member,
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function registration(string $email, string $note = 'Youth Meeting, Deacons’ School Advanced'): array
    {
        return Registrations::parent([
            'account' => ['firstName' => 'Mina', 'lastName' => 'Hanna', 'email' => $email],
            'registration' => ['registeredAs' => 'member', 'note' => $note],
        ]);
    }

    private function registered(string $email): string
    {
        $this->jsonRequest('POST', '/api/auth/register', $this->registration($email));
        self::assertResponseStatusCodeSame(202);
        $this->browser()->getCookieJar()->clear();

        return Type::of(
            new Database(self::getContainer())
                ->connection()
                ->fetchOne('SELECT id::text FROM identity.app_user WHERE email = ?', [$email]),
        )->toString();
    }

    private function racingLink(string $email): void
    {
        $connection = new Database(self::getContainer())->connection();

        $connection->executeStatement(<<<SQL
            CREATE FUNCTION identity.member_race() RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN
              IF pg_trigger_depth() = 1 AND NEW.person_id IS NOT NULL AND OLD.person_id IS NULL THEN
                UPDATE identity.app_user SET person_id = NEW.person_id, age_check = 'sixteenOrOlder'
                WHERE email = '{$email}';
              END IF;
              RETURN NEW;
            END \$\$
            SQL);

        $connection->executeStatement(
            'CREATE TRIGGER member_race BEFORE UPDATE ON identity.app_user FOR EACH ROW EXECUTE FUNCTION identity.member_race()',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $email, string $columns): array
    {
        $row = new Database(self::getContainer())
            ->connection()
            ->fetchAssociative('SELECT ' . $columns . ' FROM identity.app_user WHERE email = ?', [$email]);

        self::assertIsArray($row);

        return $row;
    }
}
