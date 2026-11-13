<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Identity;

use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Domain\Entity\MemberLinks;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class MemberAccountsControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testAdministratorsSeeTheLinkedAccountsWithGroupsAndAgeChecks(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        $accounts = $this->members();
        $this->signInWithRoles('mariam@example.org', 'administrator');

        $this->jsonRequest('GET', '/api/member-accounts');

        self::assertResponseIsSuccessful();
        self::assertSame(['Hoda Farag', 'Nadia Mansour', 'Nevine Boulos', 'Samir Botros'], $this->names());
        $this->assertJsonPath('items.0.isParent', true);
        $this->assertJsonPath('items.2.member.groups', ['Bible Study', 'Coptic Language Course › Level 1']);
        $this->assertJsonPath('items.2.ageCheck', 'sixteenOrOlder');
        $this->assertJsonPath('items.3.ageCheck', 'guardianConsent');
        $this->assertJsonPath('items.3.guardianConsentNote', 'Father agreed after the liturgy.');
        $this->assertJsonPath('items.2.member.id', $accounts['nevine']);
        $body = (string) $this->browser()->getResponse()->getContent();

        foreach (['password', 'registration', 'authVersion', 'session'] as $hidden) {
            self::assertStringNotContainsString($hidden, $body);
        }

        $this->browser()->getCookieJar()->clear();
        $this->signInWithRoles('christine@example.org', 'service_leader');
        $this->jsonRequest('GET', '/api/member-accounts');
        $this->assertProblem(403, 'accounts.adminOnly');
    }

    public function testUnlinkingAMemberOnlyAccountLeavesItWithoutTheMemberPortal(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        $this->members();
        $this->signInAs('nevine.boulos@example.org', SignInAccounts::TYPED);
        $nevineSession = $this->browser()->getCookieJar()->all();
        $this->browser()->getCookieJar()->clear();
        $this->signInWithRoles('mariam@example.org', 'administrator');
        $nevineAccount = $this->accountId('nevine.boulos@example.org');

        $this->jsonRequest('POST', '/api/member-accounts/' . $nevineAccount . '/unlink', []);
        self::assertResponseStatusCodeSame(204);
        $this->jsonRequest('POST', '/api/member-accounts/' . $nevineAccount . '/unlink', []);
        $this->assertProblem(409, 'accounts.notLinked');
        $this->jsonRequest('POST', '/api/member-accounts/' . Uuid::v7()->toRfc4122() . '/unlink', []);
        $this->assertProblem(404, 'notFound.user');

        $this->restore($nevineSession);
        $this->jsonRequest('GET', '/api/member/groups');
        $this->assertProblem(403, 'forbidden');
    }

    public function testUnlinkingAParentKeepsTheParentPortal(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        $this->members();
        $this->signInWithRoles('mariam@example.org', 'administrator');

        $this->jsonRequest('POST', '/api/member-accounts/' . $this->accountId('hoda@example.org') . '/unlink', []);
        self::assertResponseStatusCodeSame(204);

        $this->browser()->getCookieJar()->clear();
        $this->signInAs('hoda@example.org', SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/me');
        $this->assertJsonPath('member', null);
        $this->jsonRequest('GET', '/api/parent/children');
        self::assertResponseIsSuccessful();
    }

    /**
     * @return array{nevine: string}
     */
    private function members(): array
    {
        $bible = Structure::service('Bible Study');
        $coptic = Structure::service('Coptic Language Course');
        $level1 = Structure::stage($coptic, 'Level 1');
        new Database(self::getContainer())->persisted($bible, $coptic, $level1);
        $rosters = new Rosters(self::getContainer());
        $signIn = new SignInAccounts(self::getContainer());
        $links = new MemberLinks(self::getContainer());
        $ids = [];

        foreach ([
            ['hoda@example.org',          'Hoda',   'Farag',   'sixteenOrOlder',  null],
            ['nadia@example.org',         'Nadia',  'Mansour', 'sixteenOrOlder',  null],
            ['nevine.boulos@example.org', 'Nevine', 'Boulos',  'sixteenOrOlder',  null],
            ['samir@example.org',         'Samir',  'Botros',  'guardianConsent', 'Father agreed after the liturgy.'],
        ] as [$email, $first, $last, $age, $note]) {
            $person = $rosters->person($first, $last);
            $rosters->place($person, $bible, null, '2026-09-01');
            $rosters->place($person, $coptic, $level1, '2026-09-01');
            $account = $signIn->add($email, ['display_name' => $first . ' ' . $last]);
            $links->link($account, $person->id()->toRfc4122(), $age, $note);
            $ids[$first] = $person->id()->toRfc4122();
        }

        new RoleGrants(self::getContainer())->grant($this->accountId('hoda@example.org'), 'parent');
        $servant = $signIn->add('daniel@example.org', ['display_name' => 'Daniel Michael']);
        new RoleGrants(self::getContainer())->grant($servant, 'servant');
        $links->link($servant, $rosters->person('Daniel', 'Michael')->id()->toRfc4122());

        return ['nevine' => $ids['Nevine']];
    }

    /**
     * @param array<array-key, Cookie> $cookies
     */
    private function restore(array $cookies): void
    {
        $jar = $this->browser()->getCookieJar();
        $jar->clear();

        foreach ($cookies as $cookie) {
            $jar->set($cookie);
        }
    }

    private function accountId(string $email): string
    {
        return Type::of(
            new Database(self::getContainer())
                ->connection()
                ->fetchOne('SELECT id::text FROM identity.app_user WHERE email = ?', [$email]),
        )->toString();
    }

    /**
     * @return list<string>
     */
    private function names(): array
    {
        return array_values(array_map(
            static fn (mixed $item): string => Type::of(JsonBody::asArray($item, 'item')['name'] ?? null)->toString(),
            JsonBody::asArray($this->responseJson()['items'] ?? null, 'items'),
        ));
    }
}
