<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Access\Application\Service;

use App\Library\Strings\Model\Text;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Domain\Entity\MemberLinks;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Uid\Uuid;

final class AccessCheckMatrixTest extends ApiTestCase
{
    private const string OPEN_TO_EVERY_ACCOUNT = '#^/api/(auth|health|me|member)(/|$)#';

    private const int FORBIDDEN = 403;

    private const int SHAPE_REFUSED = 422;

    public function testAMemberOnlyAccountIsRefusedOnEveryRouteThatNeedsAPermission(): void
    {
        $account = new SignInAccounts(self::getContainer())->add('nevine@example.org');

        $person = new Families(self::getContainer())
            ->child('Nevine')
            ->id()
            ->toRfc4122();

        new MemberLinks(self::getContainer())->link($account, $person);
        $routes = $this->guardedRoutes();
        $this->signInAs('nevine@example.org', SignInAccounts::TYPED);

        self::assertGreaterThan(50, \count($routes));

        foreach ($routes as [$method, $path]) {
            $this->jsonRequest($method, $path, []);
            $status = $this->browser()->getResponse()->getStatusCode();
            self::assertContains($status, [self::FORBIDDEN, self::SHAPE_REFUSED], $method . ' ' . $path);
        }
    }

    /**
     * @return list<array{string, string}>
     */
    private function guardedRoutes(): array
    {
        $router = self::getContainer()->get(RouterInterface::class);
        self::assertInstanceOf(RouterInterface::class, $router);
        $guarded = [];

        foreach ($router->getRouteCollection() as $route) {
            if (!self::needsAPermission($route)) {
                continue;
            }

            $methods = $route->getMethods() === [] ? ['GET'] : $route->getMethods();

            foreach ($methods as $method) {
                $guarded[] = [$method, self::pathOf($route)];
            }
        }

        return $guarded;
    }

    private static function needsAPermission(Route $route): bool
    {
        $controller = Type::of($route->getDefault('_controller'))->toStringOrNull() ?? '';
        $isApi = Text::of($controller)->startsWith('App\\Interface\\Api\\');

        return $isApi && !Text::of($route->getPath())->matches(self::OPEN_TO_EVERY_ACCOUNT);
    }

    private static function pathOf(Route $route): string
    {
        return Text::of($route->getPath())
            ->replaceMatches('/\{date\}/', replacement: '2026-11-08')
            ->replaceMatches('/\{\w+\}/', replacement: Uuid::v7()->toRfc4122())
            ->toString();
    }
}
