<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Kernel\Service;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class KernelProductionSessionTest extends KernelTestCase
{
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
