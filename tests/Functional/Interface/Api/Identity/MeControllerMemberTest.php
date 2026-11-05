<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Identity;

use App\Context\People\Domain\Entity\Person;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Domain\Entity\MemberLinks;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class MeControllerMemberTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testAMemberCountsTheCurrentGroupsInOpenServicesAndStages(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        $nevine = $this->member('nevine@example.org', 'Nevine');
        $this->signInAs('nevine@example.org', SignInAccounts::TYPED);

        $this->jsonRequest('GET', '/api/me');

        self::assertSame(['account', 'roles', 'permissions', 'today', 'member'], array_keys($this->responseJson()));
        $this->assertJsonPath('member', ['personId' => $nevine->id()->toRfc4122(), 'groupCount' => 2]);
        $this->assertJsonPath('roles', []);

        new Database(self::getContainer())
            ->connection()
            ->update('people.person', ['status' => 'inactive'], ['id' => $nevine->id()->toRfc4122()]);

        $this->jsonRequest('GET', '/api/me');
        $this->assertJsonPath('member.groupCount', 0);
    }

    public function testStaffCanBeMembersAndParentsWithoutARecordAreNot(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        $rosters = new Rosters(self::getContainer());
        $youth = Structure::service('Youth Meeting');
        new Database(self::getContainer())->persisted($youth);
        $peter = $rosters->person('Peter', 'Botros');
        $rosters->place($peter, $youth, null, '2026-09-01');
        $servant = new SignInAccounts(self::getContainer())->add('peter@example.org');
        new RoleGrants(self::getContainer())->grant($servant, 'servant');
        new MemberLinks(self::getContainer())->link($servant, $peter->id()->toRfc4122());
        $parent = new SignInAccounts(self::getContainer())->add('hoda@example.org');
        new RoleGrants(self::getContainer())->grant($parent, 'parent');

        $this->signInAs('peter@example.org', SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/me');
        $this->assertJsonPath('member.groupCount', 1);

        $this->browser()->getCookieJar()->clear();
        $this->signInAs('hoda@example.org', SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/me');
        $this->assertJsonPath('member', null);
    }

    private function member(string $email, string $firstName): Person
    {
        $bible = Structure::service('Bible Study');
        $coptic = Structure::service('Coptic Language Course');
        $level1 = Structure::stage($coptic, 'Level 1');
        $closed = Structure::stage($coptic, 'Level 2', position: 1);
        $closed->archive(Structure::now());
        $old = Structure::service('Old choir');
        $old->archive(Structure::now());
        new Database(self::getContainer())->persisted($bible, $coptic, $level1, $closed, $old);
        $rosters = new Rosters(self::getContainer());
        $person = $rosters->person($firstName, 'Boulos');
        $rosters->place($person, $bible, null, '2026-09-01');
        $rosters->place($person, $coptic, $level1, '2026-09-01');
        $rosters->place($person, $coptic, $closed, '2026-09-01');
        $rosters->place($person, $old, null, '2026-09-01');
        $rosters->place($person, $bible, null, '2026-01-01', '2026-06-30');
        $account = new SignInAccounts(self::getContainer())->add($email);
        new MemberLinks(self::getContainer())->link($account, $person->id()->toRfc4122());

        return $person;
    }
}
