<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Identity\Domain\ValueObject;

use App\Context\Identity\Domain\ValueObject\Email;
use App\Tests\Support\Library\Problem\Exception\ThrownFieldErrors;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    public function testAnEmailIsTrimmedAndLowerCased(): void
    {
        self::assertSame('sara.hanna@example.org', Email::of('  Sara.Hanna@Example.org ')->value);
        self::assertSame('sara.hanna@example.org', Email::normalized("\u{00A0}SARA.HANNA@EXAMPLE.ORG\n"));
    }

    public function testAMissingEmailIsReported(): void
    {
        self::assertSame(
            ['email' => ['code' => 'accounts.emailRequired']],
            ThrownFieldErrors::of(static fn (): Email => Email::of('   ')),
        );
    }

    public function testAnAddressWithoutAtAndDomainIsInvalid(): void
    {
        self::assertSame(
            ['email' => ['code' => 'accounts.emailInvalid']],
            ThrownFieldErrors::of(static fn (): Email => Email::of('sara at example')),
        );

        self::assertSame(
            ['email' => ['code' => 'accounts.emailInvalid']],
            ThrownFieldErrors::of(static fn (): Email => Email::of('sara hanna@example.org')),
        );
    }

    public function testAnEmailHasAtMost254Characters(): void
    {
        self::assertSame(
            ['email' => ['code' => 'validation.tooLong', 'params' => ['max' => 254]]],
            ThrownFieldErrors::of(static fn (): Email => Email::of(str_repeat('a', times: 243) . '@example.org')),
        );
    }
}
