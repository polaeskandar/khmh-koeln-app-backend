<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Kernel\Service;

use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;

final class KernelProductionSessionTest extends KernelTestCase
{
    #[Override]
    protected function setUp(): void
    {
        new Filesystem()->remove(dirname(__DIR__, levels: 5) . '/var/cache/prod');
    }

    #[Override]
    protected function tearDown(): void
    {
        parent::tearDown();
        Request::setTrustedHosts([]);
    }

    public function testTheProductionSessionCookieIsAlwaysSecureAndHostBound(): void
    {
        $kernel = self::bootKernel(['environment' => 'prod', 'debug' => false]);
        $options = $kernel->getContainer()->getParameter('session.storage.options');
        self::assertIsArray($options);

        self::assertSame('__Host-khmk_session', $options['name'] ?? null);
        self::assertTrue($options['cookie_secure'] ?? null);
        self::assertTrue($options['cookie_httponly'] ?? null);
        self::assertSame('lax', $options['cookie_samesite'] ?? null);
        self::assertSame('/', $options['cookie_path'] ?? null);
        self::assertNull($options['cookie_domain'] ?? null);
    }
}
