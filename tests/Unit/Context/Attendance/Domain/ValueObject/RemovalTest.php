<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Attendance\Domain\ValueObject;

use App\Context\Attendance\Domain\ValueObject\Removal;
use App\Tests\Support\Library\Problem\Exception\ThrownFieldErrors;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RemovalTest extends TestCase
{
    public function testAReasonIsTrimmed(): void
    {
        $removal = Removal::of(new DateTimeImmutable('2026-11-08T12:10:00+01:00'), null, '  Wrong child ');

        self::assertSame('Wrong child', $removal->reason);
    }

    public function testAReasonHasALimit(): void
    {
        $at = new DateTimeImmutable('2026-11-08T12:10:00+01:00');

        self::assertSame(
            ['reason' => ['code' => 'validation.tooLong', 'params' => ['max' => 200]]],
            ThrownFieldErrors::of(static fn (): Removal => Removal::of($at, null, str_repeat('a', times: 201))),
        );

        self::assertSame(200, mb_strlen((string) Removal::of($at, null, str_repeat('ä', times: 200))->reason));
    }
}
