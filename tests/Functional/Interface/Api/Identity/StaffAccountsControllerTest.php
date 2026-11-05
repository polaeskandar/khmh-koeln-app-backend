<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Identity;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\JsonBody;
use App\Tests\Support\Logs;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Uuid;

final class StaffAccountsControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;
    use MailerAssertionsTrait;

    private const string SERVANT_ROLE = '019a3f00-0000-7000-8000-000000000003';

    public function testOnlyAdministratorsMayManageAccounts(): void
    {
        $someone = Uuid::v7()->toRfc4122();

        $calls = [
            ['GET', '/api/staff-accounts', 'forbidden', []],
            ['POST', '/api/accounts/' . $someone . '/roles', 'roles.adminOnly', ['roleId' => self::SERVANT_ROLE]],
            ['DELETE', '/api/accounts/' . $someone . '/roles/' . self::SERVANT_ROLE, 'roles.adminOnly', []],
            ['POST', '/api/accounts/' . $someone . '/password-link', 'forbidden', []],
        ];

        foreach ($calls as [$method, $path, , $body]) {
            $this->jsonRequest($method, $path, $body);
            $this->assertProblem(401, 'auth.unauthenticated');
        }

        foreach (['service_leader', 'servant', 'parent'] as $role) {
            $this->signInWithRoles($role . '@example.org', $role);

            foreach ($calls as [$method, $path, $code, $body]) {
                $this->jsonRequest($method, $path, $body);
                $this->assertProblem(403, $code);
            }

            $this->browser()->getCookieJar()->clear();
        }
    }

    public function testTheListHasStaffAndRoleLessAccountsWithDisabledOnesLast(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $this->account('bishoy@example.org', 'Bishoy Adel', [], 'servant', 'parent');
        $this->account('zeina@example.org', 'Zeina Gerges', []);
        $this->account('adel@example.org', 'Adel Fahmy', ['disabled_at' => '2026-11-01 10:00:00+01'], 'servant');
        $this->account('hoda@example.org', 'Hoda Farag', [], 'parent');
        $this->account('mina@example.org', 'Mina Wassef', ['approved_at' => null]);
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('GET', '/api/staff-accounts');

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('today', '2026-11-08');

        self::assertSame(
            [
                ['Bishoy Adel',  false],
                ['Sara Hanna',   false],
                ['Zeina Gerges', false],
                ['Adel Fahmy',   true],
            ],
            array_map(static fn (array $item): array => [
                $item['displayName'] ?? null,
                $item['disabled'] ?? null,
            ], $this->items()),
        );

        $this->assertJsonPath('items.0.roles', [
            ['id' => '019a3f00-0000-7000-8000-000000000004', 'name' => 'Parent'],
            ['id' => self::SERVANT_ROLE, 'name' => 'Servant'],
        ]);

        $this->assertJsonPath('items.0.email', 'bishoy@example.org');
        $this->assertJsonPath('items.2.roles', []);
    }

    public function testAPasswordLinkIsEmailedForADayAndWorksOnce(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $this->account('bishoy@example.org', 'Bishoy Adel', [], 'servant');
        $this->signInWithRoles('admin@example.org', 'administrator');

        $older = $this->issuedToken('bishoy@example.org');
        $token = $this->issuedToken('bishoy@example.org');
        $this->assertJsonPath('expiresAt', '2026-11-09T10:00:00+01:00');

        $this->jsonRequest('POST', '/api/auth/password-links', [
            'token' => $older,
            'password' => SignInAccounts::MISTYPED,
        ]);

        $this->assertProblem(422, 'auth.resetLinkInvalid');

        $this->jsonRequest('POST', '/api/auth/password-links', [
            'token' => $token,
            'password' => SignInAccounts::MISTYPED,
        ]);

        self::assertResponseStatusCodeSame(204);

        $this->jsonRequest('POST', '/api/auth/password-links', [
            'token' => $token,
            'password' => SignInAccounts::TYPED,
        ]);

        $this->assertProblem(422, 'auth.resetLinkInvalid');
        self::assertSame([], new Logs(self::getContainer())->containing($token));
    }

    public function testOnlyActiveAccountsGetAPasswordLink(): void
    {
        $pending = $this->account('mina@example.org', 'Mina Wassef', ['approved_at' => null]);
        $disabled = $this->account('adel@example.org', 'Adel Fahmy', ['disabled_at' => '2026-11-01 10:00:00+01']);
        $this->signInWithRoles('admin@example.org', 'administrator');

        foreach ([$pending, $disabled] as $account) {
            $this->jsonRequest('POST', '/api/accounts/' . $account . '/password-link');
            $this->assertProblem(409, 'accounts.passwordLinkUnavailable');
        }

        $this->jsonRequest('POST', '/api/accounts/' . Uuid::v7()->toRfc4122() . '/password-link');
        $this->assertProblem(404, 'notFound.user');
    }

    /**
     * @param array<string, string|null> $columns
     */
    private function account(string $email, string $name, array $columns, string ...$roles): string
    {
        $id = new SignInAccounts(self::getContainer())->add($email, ['display_name' => $name, ...$columns]);
        new RoleGrants(self::getContainer())->grant($id, ...$roles);

        return $id;
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function items(): array
    {
        $items = JsonBody::asArray($this->responseJson()['items'] ?? null, 'items');

        return array_values(array_filter($items, is_array(...)));
    }

    private function issuedToken(string $email): string
    {
        $id = $this->idOf($email);
        $this->jsonRequest('POST', '/api/accounts/' . $id . '/password-link');

        self::assertResponseStatusCodeSame(201);
        self::assertSame(['expiresAt'], array_keys($this->responseJson()));
        $message = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $message);
        self::assertSame($email, ($message->getTo()[0] ?? null)?->getAddress());
        $matches = [];
        self::assertSame(1, preg_match('/#token=([A-Za-z0-9_-]{43})/', (string) $message->getTextBody(), $matches));

        return $matches[1] ?? '';
    }

    private function idOf(string $email): string
    {
        $this->jsonRequest('GET', '/api/staff-accounts');

        foreach ($this->items() as $item) {
            if (($item['email'] ?? null) === $email) {
                return is_string($item['id'] ?? null) ? $item['id'] : '';
            }
        }

        return '';
    }
}
