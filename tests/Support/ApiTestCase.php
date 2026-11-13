<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Platform\OpenApi\ResponseContract;
use Override;
use SensitiveParameter;
use stdClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

abstract class ApiTestCase extends WebTestCase
{
    private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        self::createClient(server: ['HTTPS' => 'on']);
    }

    /**
     * @param array<array-key, mixed> $body
     * @param array<string, string> $headers
     */
    protected function jsonRequest(string $method, string $uri, array $body = [], array $headers = []): Response
    {
        $server = [
            'HTTP_ACCEPT' => 'application/json',
            ...array_combine(
                array_map(
                    static fn (string $header): string => 'HTTP_' . strtr(strtoupper($header), from: '-', to: '_'),
                    array_keys($headers),
                ),
                $headers,
            ),
        ];

        $content = null;

        if (!in_array(strtoupper($method), self::SAFE_METHODS, strict: true)) {
            $server['CONTENT_TYPE'] = 'application/json';
            $content = json_encode($body === [] ? new stdClass() : $body, JSON_THROW_ON_ERROR);
        }

        $this->browser()->request($method, $uri, server: $server, content: $content);
        $router = self::getContainer()->get(RouterInterface::class);
        self::assertInstanceOf(RouterInterface::class, $router);
        ResponseContract::assertKept($router, $this->browser()->getRequest(), $this->browser()->getResponse());

        return $this->browser()->getResponse();
    }

    /**
     * @param array<string, bool|int|string> $options
     */
    protected function startClient(array $options): void
    {
        self::ensureKernelShutdown();
        self::createClient($options, ['HTTPS' => 'on']);
    }

    protected function signInAs(string $email, #[SensitiveParameter] string $secret): void
    {
        $response = $this->jsonRequest('POST', '/api/auth/sign-in', [
            'username' => Accounts::usernameOf($email),
            'password' => $secret,
        ]);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
    }

    protected function signInWithRoles(string $email, string ...$roles): string
    {
        $id = new SignInAccounts(self::getContainer())->add($email);
        new RoleGrants(self::getContainer())->grant($id, ...$roles);
        $this->signInAs($email, SignInAccounts::TYPED);

        return $id;
    }

    protected function signInNamed(string $email, string $name, string ...$roles): string
    {
        $id = new SignInAccounts(self::getContainer())->add($email, ['display_name' => $name]);
        new RoleGrants(self::getContainer())->grant($id, ...$roles);
        $this->signInAs($email, SignInAccounts::TYPED);

        return $id;
    }

    protected function browser(): KernelBrowser
    {
        $browser = self::getClient();
        self::assertInstanceOf(KernelBrowser::class, $browser);

        return $browser;
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function responseJson(): array
    {
        $content = (string) $this->browser()->getResponse()->getContent();

        return JsonBody::asArray(
            json_decode($content, associative: true, flags: JSON_THROW_ON_ERROR),
            'The response body',
        );
    }

    protected function assertJsonPath(string $path, mixed $expected): void
    {
        self::assertSame($expected, JsonBody::valueAt($this->responseJson(), explode('.', $path), $path));
    }

    /**
     * @param array<string, string|int|float|bool|null> $params
     */
    protected function assertProblem(int $status, string $code, array $params = []): void
    {
        $response = $this->browser()->getResponse();
        self::assertSame($status, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        $body = $this->responseJson();
        self::assertSame($status, $body['status'] ?? null);
        self::assertSame($code, $body['code'] ?? null);

        self::assertSame(
            JsonBody::sorted($params),
            JsonBody::sorted(JsonBody::asArray($body['params'] ?? null, '"params"')),
        );
    }

    /**
     * @param array<string, string|int|float|bool|null> $params
     */
    protected function assertFieldError(string $field, string $code, array $params = []): void
    {
        $fieldErrors = JsonBody::asArray($this->responseJson()['fieldErrors'] ?? null, '"fieldErrors"');
        $error = JsonBody::asArray($fieldErrors[$field] ?? null, $field);

        self::assertSame($code, $error['code'] ?? null, $field);

        self::assertSame(
            JsonBody::sorted($params),
            JsonBody::sorted(JsonBody::asArray($error['params'] ?? null, $field)),
            $field,
        );
    }
}
