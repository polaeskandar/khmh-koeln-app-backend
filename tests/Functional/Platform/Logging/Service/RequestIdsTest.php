<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Logging\Service;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Logs;
use Symfony\Component\Uid\Uuid;

final class RequestIdsTest extends ApiTestCase
{
    public function testEveryResponseAndItsLogRecordsCarryTheSameIdAndNoAccountData(): void
    {
        $account = new SignInAccounts(self::getContainer())->add('daniel.mikhail@example.org');
        new RoleGrants(self::getContainer())->grant($account, 'servant');
        $this->signInAs('daniel.mikhail@example.org', SignInAccounts::TYPED);

        $this->jsonRequest('GET', '/api/me');

        $id = (string) $this->browser()->getResponse()->headers->get('X-Request-Id');
        self::assertTrue(Uuid::isValid($id));
        $records = new Logs(self::getContainer())->containing($id);
        self::assertNotSame([], $records);

        foreach ($records as $record) {
            self::assertStringNotContainsString($account, $record);
            self::assertStringNotContainsString('daniel.mikhail', $record);
            self::assertStringNotContainsString('Sara Hanna', $record);
        }
    }

    public function testAValidIdFromTheProxyIsKeptAndAnInvalidOneReplaced(): void
    {
        $this->jsonRequest('GET', '/api/health', headers: ['X-Request-Id' => 'proxy-7f3a-01']);
        self::assertSame('proxy-7f3a-01', $this->browser()->getResponse()->headers->get('X-Request-Id'));

        foreach ([str_repeat('a', times: 65), 'proxy 7f3a', 'mina@example.org'] as $invalid) {
            $this->jsonRequest('GET', '/api/health', headers: ['X-Request-Id' => $invalid]);
            $id = (string) $this->browser()->getResponse()->headers->get('X-Request-Id');

            self::assertNotSame($invalid, $id);
            self::assertTrue(Uuid::isValid($id));
        }
    }
}
