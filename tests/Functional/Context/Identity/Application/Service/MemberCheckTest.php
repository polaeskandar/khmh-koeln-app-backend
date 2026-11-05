<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Identity\Application\Service;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Domain\Entity\MemberLinks;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

final class MemberCheckTest extends ApiTestCase
{
    private const string PROBE = '/api/member/testing';

    public function testALinkedAccountWithoutRolesReachesOnlyTheMemberPortal(): void
    {
        [, $person] = $this->linked('nevine@example.org');
        $this->signInAs('nevine@example.org', SignInAccounts::TYPED);

        $this->jsonRequest('GET', self::PROBE);
        $this->assertJsonPath('personId', $person);

        $this->jsonRequest('GET', '/api/services');
        $this->assertProblem(403, 'forbidden');
        $this->jsonRequest('GET', '/api/parent/children');
        $this->assertProblem(403, 'forbidden');
    }

    public function testALinkedParentReachesBothPortalsAndAnUnlinkedServantNeither(): void
    {
        [$hoda] = $this->linked('hoda@example.org');
        new RoleGrants(self::getContainer())->grant($hoda, 'parent');
        $daniel = new SignInAccounts(self::getContainer())->add('daniel@example.org');
        new RoleGrants(self::getContainer())->grant($daniel, 'servant');

        $this->signInAs('hoda@example.org', SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/parent/children');
        self::assertResponseIsSuccessful();
        $this->jsonRequest('GET', self::PROBE);
        self::assertResponseIsSuccessful();

        $this->browser()->getCookieJar()->clear();
        $this->jsonRequest('GET', self::PROBE);
        $this->assertProblem(401, 'auth.unauthenticated');
        $this->signInAs('daniel@example.org', SignInAccounts::TYPED);
        $this->jsonRequest('GET', self::PROBE);
        $this->assertProblem(403, 'forbidden');
    }

    public function testUnlinkingClosesThePortalOnTheNextRequestWithoutSigningOut(): void
    {
        [$nevine] = $this->linked('nevine@example.org');
        new RoleGrants(self::getContainer())->grant($nevine, 'parent');
        $this->signInAs('nevine@example.org', SignInAccounts::TYPED);
        $this->jsonRequest('GET', self::PROBE);
        self::assertResponseIsSuccessful();

        $kernel = $this->browser()->getKernel();
        self::assertInstanceOf(KernelInterface::class, $kernel);

        new CommandTester(new Application($kernel)->find('app:member:unlink'))->execute([
            'email' => 'nevine@example.org',
        ]);

        $this->jsonRequest('GET', self::PROBE);
        $this->assertProblem(403, 'forbidden');
        $this->jsonRequest('GET', '/api/me');
        self::assertResponseIsSuccessful();
    }

    /**
     * @return array{string, string}
     */
    private function linked(string $email): array
    {
        $account = new SignInAccounts(self::getContainer())->add($email);

        $person = new Families(self::getContainer())
            ->child('Nevine')
            ->id()
            ->toRfc4122();

        new MemberLinks(self::getContainer())->link($account, $person);

        return [$account, $person];
    }
}
