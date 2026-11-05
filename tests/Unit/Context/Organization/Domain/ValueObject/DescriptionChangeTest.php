<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Organization\Domain\ValueObject;

use App\Context\Organization\Domain\ValueObject\DescriptionChange;
use PHPUnit\Framework\TestCase;

final class DescriptionChangeTest extends TestCase
{
    public function testKeepingLeavesTheCurrentDescription(): void
    {
        self::assertSame('After the liturgy', DescriptionChange::keep()->appliedTo('After the liturgy'));
    }

    public function testAReplacementWinsAndNullClears(): void
    {
        self::assertSame('Fridays', DescriptionChange::to('Fridays')->appliedTo('After the liturgy'));
        self::assertNull(DescriptionChange::to(null)->appliedTo('After the liturgy'));
    }
}
