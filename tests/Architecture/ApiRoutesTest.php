<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use App\Library\Strings\Model\Text;
use App\Library\Types\Model\Type;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

final class ApiRoutesTest extends KernelTestCase
{
    public function testEveryApplicationRouteLivesUnderTheApiPrefix(): void
    {
        $router = self::getContainer()->get(RouterInterface::class);
        self::assertInstanceOf(RouterInterface::class, $router);

        foreach ($router->getRouteCollection() as $name => $route) {
            if (!self::isApplicationController($route->getDefault('_controller'))) {
                continue;
            }

            $path = $route->getPath();
            self::assertTrue(
                $path === '/api' || str_starts_with($path, '/api/'),
                sprintf('Route "%s" (%s) is outside /api.', $name, $path),
            );
        }
    }

    private static function isApplicationController(mixed $controller): bool
    {
        return Text::of(Type::of($controller)->toStringOrNull() ?? '')->startsWith('App\\');
    }
}
