<?php

declare(strict_types=1);

namespace App\Tests\Unit\Platform\Http\Service;

use App\Platform\Http\Service\DevelopmentOrigins;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DevelopmentOriginsTest extends TestCase
{
    public function testTheVariableBecomesAListOfOrigins(): void
    {
        $origins = new DevelopmentOrigins('http://localhost:3000,http://127.0.0.1:3000')->all();

        self::assertSame(['http://localhost:3000', 'http://127.0.0.1:3000'], $origins);
        self::assertSame([], new DevelopmentOrigins('')->all());
    }

    public function testAWildcardIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DevelopmentOrigins('*');
    }
}
