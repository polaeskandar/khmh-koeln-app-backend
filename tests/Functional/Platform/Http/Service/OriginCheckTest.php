<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Http\Service;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use PHPUnit\Framework\Attributes\DataProvider;

final class OriginCheckTest extends ApiTestCase
{
    #[DataProvider('foreignOrigins')]
    public function testAForeignOriginIsForbiddenBeforeSignInIsTried(string $origin): void
    {
        new SignInAccounts(self::getContainer())->add('christine.adel@example.org');
        $this->browser()->disableReboot();

        foreach (range(start: 1, end: 6) as $attempt) {
            $this->jsonRequest(
                'POST',
                '/api/auth/sign-in',
                [
                    'email' => 'christine.adel@example.org',
                    'password' => 'wrong ' . $attempt,
                ],
                ['Origin' => $origin],
            );

            $this->assertProblem(403, 'forbidden');
        }

        $this->signInAs('christine.adel@example.org', SignInAccounts::TYPED);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function foreignOrigins(): iterable
    {
        yield 'another site' => ['https://evil.example'];
        yield 'opaque' => ['null'];
        yield 'look-alike' => ['https://app.khmk-koeln.com.evil.example'];
        yield 'plain http' => ['http://app.khmk-koeln.com'];
    }

    public function testTheAppsOwnOriginAndNoOriginProceed(): void
    {
        new SignInAccounts(self::getContainer())->add('christine.adel@example.org');

        $this->jsonRequest(
            'POST',
            '/api/auth/sign-in',
            [
                'email' => 'christine.adel@example.org',
                'password' => SignInAccounts::TYPED,
            ],
            ['Origin' => 'https://app.khmk-koeln.com'],
        );

        self::assertResponseStatusCodeSame(204);

        $this->jsonRequest('POST', '/api/auth/sign-out');

        self::assertResponseStatusCodeSame(204);
    }

    public function testReadingIsNeverBlocked(): void
    {
        $this->jsonRequest('GET', '/api/health', headers: ['Origin' => 'https://evil.example']);

        self::assertResponseIsSuccessful();
    }
}
