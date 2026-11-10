<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Types\Model;

use App\Library\Types\Model\Type;
use PHPUnit\Framework\TestCase;

final class TypeTest extends TestCase
{
    public function testStringIsReturnedOnlyForAString(): void
    {
        self::assertSame('de', Type::of('de')->toStringOrNull());
        self::assertNull(Type::of(1)->toStringOrNull());
        self::assertNull(Type::of(null)->toStringOrNull());
    }

    public function testScalarIsReturnedOnlyForAScalar(): void
    {
        self::assertSame(120, Type::of(120)->toScalarOrNull());
        self::assertFalse(Type::of(false)->toScalarOrNull());
        self::assertNull(Type::of(['a'])->toScalarOrNull());
        self::assertNull(Type::of(null)->toScalarOrNull());
    }

    public function testAStringIsRequiredWhereOneMustBe(): void
    {
        self::assertSame('de', Type::of('de')->toString());

        $this->expectException(\UnexpectedValueException::class);
        Type::of(1)->toString();
    }

    public function testAStringIsRequiredAtAKeyOfAMap(): void
    {
        self::assertSame('privacy.notOpen', Type::of(['done' => 'privacy.notOpen'])->toStringAt('done'));

        $this->expectException(\UnexpectedValueException::class);
        Type::of(['done' => 'privacy.notOpen'])->toStringAt('open');
    }

    public function testAStringIsRequiredAtAKeyOnlyOfAMap(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        Type::of('privacy.notOpen')->toStringAt('done');
    }

    public function testAnInstanceIsRequiredWhereOneMustBe(): void
    {
        $date = new \DateTimeImmutable('2026-11-08');

        self::assertSame($date, Type::of($date)->toInstanceOf(\DateTimeImmutable::class));

        $this->expectException(\UnexpectedValueException::class);
        Type::of('2026-11-08')->toInstanceOf(\DateTimeImmutable::class);
    }
}
