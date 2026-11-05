<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Types\Model;

use App\Library\Types\Model\ListOf;
use ArrayObject;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class ListOfTest extends TestCase
{
    public function testInstancesOfTheClassBecomeAList(): void
    {
        $first = new ArrayObject();
        $second = new ArrayObject();

        self::assertSame([$first, $second], ListOf::instances(['a' => $first, 'b' => $second], ArrayObject::class));
    }

    public function testAnythingElseIsRefused(): void
    {
        $this->expectException(UnexpectedValueException::class);

        ListOf::instances([new ArrayObject(), 'text'], ArrayObject::class);
    }

    public function testANonListIsRefused(): void
    {
        $this->expectException(UnexpectedValueException::class);

        ListOf::instances('text', ArrayObject::class);
    }
}
