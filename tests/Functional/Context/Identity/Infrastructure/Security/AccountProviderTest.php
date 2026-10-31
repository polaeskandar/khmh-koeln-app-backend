<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Identity\Infrastructure\Security;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;

final class AccountProviderTest extends ApiTestCase
{
    /**
     * @param array<string, string> $change
     */
    #[DataProvider('changes')]
    public function testASessionLastsUntilTheAccountStopsBeingWhatItSignedInAs(array $change, int $status): void
    {
        $id = new SignInAccounts(self::getContainer())->add('christine.adel@example.org');
        $this->signInAs('christine.adel@example.org', SignInAccounts::TYPED);

        new Database(self::getContainer())
            ->connection()
            ->update('identity.app_user', $change, ['id' => $id]);

        $this->jsonRequest('GET', '/api/testing/session');

        self::assertResponseStatusCodeSame($status);
    }

    /**
     * @return iterable<string, array{array<string, string>, int}>
     */
    public static function changes(): iterable
    {
        yield 'disabled' => [['disabled_at' => '2026-11-09 08:00:00+01'], 401];
        yield 'new password' => [['password_hash' => 'a-different-hash'], 401];
        yield 'new display name' => [['display_name' => 'Christine A.'], 200];
        yield 'new language' => [['language' => 'ar'], 200];
    }
}
