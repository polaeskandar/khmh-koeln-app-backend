<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use App\Interface\Application\Security\Model\RequiresAccess;
use App\Library\Types\Model\Type;
use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

final class ControllerAuthorizationTest extends KernelTestCase
{
    private const array WITHOUT_REQUIRED_ACCESS = [
        'App\Interface\Api\Health\HealthController::live' => 'Public liveness probe.',
        'App\Interface\Api\Health\HealthController::ready' => 'Public readiness probe.',
        'App\Interface\Api\Auth\SessionController::signIn' => 'Public: signing in.',
        'App\Interface\Api\Auth\SessionController::signOut' => 'Public: signing out.',
        'App\Interface\Api\Identity\MeController::show' => 'Any signed-in account reads itself.',
    ];

    public function testEveryApiActionRequiresAccessOrSaysWhyNot(): void
    {
        $router = self::getContainer()->get(RouterInterface::class);
        self::assertInstanceOf(RouterInterface::class, $router);

        foreach ($router->getRouteCollection() as $route) {
            $controller = Type::of($route->getDefault('_controller'))->toStringOrNull() ?? '';

            if (!str_starts_with($controller, 'App\\Interface\\')) {
                continue;
            }

            self::assertTrue(
                self::requiresAccess($controller) || array_key_exists($controller, self::WITHOUT_REQUIRED_ACCESS),
                sprintf('%s needs #[RequiresAccess] or an entry with a reason in this test.', $controller),
            );
        }
    }

    private static function requiresAccess(string $controller): bool
    {
        $method = ReflectionMethod::createFromMethodName(
            str_contains($controller, '::') ? $controller : $controller . '::__invoke',
        );

        return (
            $method->getAttributes(RequiresAccess::class) !== []
            || $method->getDeclaringClass()->getAttributes(RequiresAccess::class) !== []
        );
    }
}
