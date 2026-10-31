<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Identity\Domain\ValueObject;

use App\Context\Identity\Domain\ValueObject\PlainPassword;
use App\Tests\Support\Library\Problem\Exception\ThrownFieldErrors;
use PHPUnit\Framework\TestCase;

final class PlainPasswordTest extends TestCase
{
    public function testAPasswordOf10To200CharactersIsKeptAsTyped(): void
    {
        self::assertSame(' ten chars', PlainPassword::of(' ten chars')->value);
        self::assertSame(200, PlainPassword::of(str_repeat('ä', times: 200))->value |> mb_strlen(...));
    }

    public function testAPasswordOf9CharactersIsTooShort(): void
    {
        self::assertSame(
            ['password' => ['code' => 'accounts.passwordTooShort', 'params' => ['min' => 10]]],
            ThrownFieldErrors::of(static fn (): PlainPassword => PlainPassword::of('nine char')),
        );
    }

    public function testAPasswordOf201CharactersIsTooLong(): void
    {
        self::assertSame(
            ['password' => ['code' => 'validation.tooLong', 'params' => ['max' => 200]]],
            ThrownFieldErrors::of(static fn (): PlainPassword => PlainPassword::of(str_repeat('a', times: 201))),
        );
    }
}
