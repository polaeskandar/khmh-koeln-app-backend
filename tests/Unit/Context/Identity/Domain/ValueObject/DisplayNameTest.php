<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Identity\Domain\ValueObject;

use App\Context\Identity\Domain\ValueObject\DisplayName;
use App\Tests\Support\Library\Problem\Exception\ThrownFieldErrors;
use PHPUnit\Framework\TestCase;

final class DisplayNameTest extends TestCase
{
    public function testADisplayNameIsCleaned(): void
    {
        self::assertSame('Sara Hanna', DisplayName::of("  Sara \u{00A0} Hanna ")->value);
    }

    public function testABlankDisplayNameIsMissing(): void
    {
        self::assertSame(
            ['displayName' => ['code' => 'accounts.nameRequired']],
            ThrownFieldErrors::of(static fn (): DisplayName => DisplayName::of('  ')),
        );
    }

    public function testADisplayNameHasAtMost120Characters(): void
    {
        self::assertSame(
            ['displayName' => ['code' => 'validation.tooLong', 'params' => ['max' => 120]]],
            ThrownFieldErrors::of(static fn (): DisplayName => DisplayName::of(str_repeat('a', times: 121))),
        );
    }
}
