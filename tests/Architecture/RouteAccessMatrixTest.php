<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Uid\Uuid;

final class RouteAccessMatrixTest extends ApiTestCase
{
    private const array OPEN_TO_NON_STAFF = ['/api/health', '/api/auth/', '/api/me', '/api/parent/', '/api/test/'];

    public function testAnonymousCallersAreUnauthenticatedOnEveryStaffRoute(): void
    {
        foreach ($this->staffCalls() as $label => [$method, $path]) {
            $this->jsonRequest($method, $path);

            $this->assertProblem(401, 'auth.unauthenticated');
            self::assertTrue(true, $label);
        }
    }

    public function testParentsAreForbiddenOnEveryStaffRoute(): void
    {
        $id = new SignInAccounts(self::getContainer())->add('mariam@example.org');
        new RoleGrants(self::getContainer())->grant($id, 'parent');
        $this->signInAs('mariam@example.org', SignInAccounts::TYPED);

        foreach ($this->staffCalls() as [$method, $path]) {
            $this->jsonRequest($method, $path);

            $this->assertProblem(403, 'forbidden');
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    private function staffCalls(): iterable
    {
        $router = self::getContainer()->get(RouterInterface::class);
        self::assertInstanceOf(RouterInterface::class, $router);

        foreach ($router->getRouteCollection() as $name => $route) {
            if (!self::isStaffRoute($route)) {
                continue;
            }

            foreach ($route->getMethods() === [] ? ['GET'] : $route->getMethods() as $method) {
                yield $name . ' ' . $method => [$method, self::withIds($route->getPath())];
            }
        }
    }

    private static function isStaffRoute(Route $route): bool
    {
        $path = $route->getPath();

        if (!str_starts_with($path, '/api')) {
            return false;
        }

        foreach (self::OPEN_TO_NON_STAFF as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return false;
            }
        }

        return true;
    }

    private static function withIds(string $path): string
    {
        return (string) preg_replace_callback('/\{[^}]+\}/', static fn (): string => Uuid::v7()->toRfc4122(), $path);
    }
}
