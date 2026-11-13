<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Identity\Domain\ValueObject;

use App\Context\Identity\Domain\ValueObject\Username;
use App\Library\Problem\Exception\InvalidInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UsernameTest extends TestCase
{
    #[DataProvider('accepted')]
    public function testATypedUsernameIsStoredInItsNormalForm(string $typed, string $stored): void
    {
        self::assertSame($stored, Username::of($typed)->value);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function accepted(): iterable
    {
        yield 'capitals' => ['Daniel', 'daniel'];
        yield 'surrounding spaces' => ['  mina.hanna ', 'mina.hanna'];
        yield 'fullwidth letters' => ['Ａdmin', 'admin'];
        yield 'every separator' => ['a.b_c-d', 'a.b_c-d'];
        yield 'digits after the first letter' => ['k2023', 'k2023'];
        yield 'three characters' => ['abc', 'abc'];
        yield 'thirty-two characters' => [str_repeat('a', times: 32), str_repeat('a', times: 32)];
    }

    /**
     * @param array<string, int> $params
     */
    #[DataProvider('refused')]
    public function testAnInvalidUsernameIsRefusedWithItsCode(string $typed, string $code, array $params = []): void
    {
        try {
            Username::of($typed);
            self::fail('The username was accepted.');
        } catch (InvalidInput $invalid) {
            $expected = $params === [] ? ['code' => $code] : ['code' => $code, 'params' => $params];
            self::assertSame(['username' => $expected], $invalid->fieldErrors);
        }
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2?: array<string, int>}>
     */
    public static function refused(): iterable
    {
        yield 'blank' => ['   ', 'accounts.usernameRequired'];
        yield 'two characters' => ['ab', 'accounts.usernameTooShort', ['min' => 3]];
        yield 'thirty-three characters' => [str_repeat('a', times: 33), 'validation.tooLong', ['max' => 32]];
        yield 'cyrillic a' => ['аdmin', 'accounts.usernameInvalid'];
        yield 'two separators in a row' => ['a..b', 'accounts.usernameInvalid'];
        yield 'ends with a separator' => ['abc.', 'accounts.usernameInvalid'];
        yield 'starts with a digit' => ['1abc', 'accounts.usernameInvalid'];
        yield 'starts with a separator' => ['.abc', 'accounts.usernameInvalid'];
        yield 'space inside' => ['mina hanna', 'accounts.usernameInvalid'];
        yield 'at sign' => ['mina@example.org', 'accounts.usernameInvalid'];
    }
}
