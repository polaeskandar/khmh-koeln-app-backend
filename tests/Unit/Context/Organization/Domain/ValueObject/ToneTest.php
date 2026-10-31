<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Organization\Domain\ValueObject;

use App\Context\Organization\Domain\ValueObject\Tone;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

final class ToneTest extends TestCase
{
    public function testTheFirstServiceGetsTheFirstTone(): void
    {
        self::assertSame(1, Tone::leastUsed(new ArrayCollection())->value);
    }

    public function testTheToneFewestServicesUseIsChosen(): void
    {
        self::assertSame(3, Tone::leastUsed(new ArrayCollection([1, 2, 2]))->value);
        self::assertSame(2, Tone::leastUsed(new ArrayCollection([1, 1, 3, 4, 5, 6, 7, 8]))->value);
    }

    public function testTheLowestToneWinsATie(): void
    {
        self::assertSame(1, Tone::leastUsed(new ArrayCollection([1, 2, 3, 4, 5, 6, 7, 8]))->value);
        self::assertSame(4, Tone::leastUsed(new ArrayCollection([8, 7, 6, 5, 3, 2, 1, 1, 2, 3, 5, 6, 7, 8]))->value);
    }
}
