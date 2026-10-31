<?php

declare(strict_types=1);

namespace App\Tests\Unit\Platform\Kernel\Service;

use App\Platform\Kernel\Service\Kernel;
use PHPUnit\Framework\TestCase;

final class KernelTest extends TestCase
{
    public function testItIsBuiltFromTheRuntimeContext(): void
    {
        $kernel = Kernel::fromRuntimeContext(['APP_ENV' => 'test', 'APP_DEBUG' => '1']);

        self::assertSame('test', $kernel->getEnvironment());
        self::assertTrue($kernel->isDebug());
        self::assertFalse(Kernel::fromRuntimeContext(['APP_ENV' => 'prod', 'APP_DEBUG' => '0'])->isDebug());
    }
}
