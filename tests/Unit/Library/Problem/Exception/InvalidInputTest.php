<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Problem\Exception;

use App\Library\Problem\Exception\InvalidInput;
use PHPUnit\Framework\TestCase;

final class InvalidInputTest extends TestCase
{
    public function testAFieldErrorCarriesItsCodeAndOnlyGivenParams(): void
    {
        self::assertSame(
            ['email' => ['code' => 'accounts.emailTaken']],
            InvalidInput::field('email', 'accounts.emailTaken')->fieldErrors,
        );

        self::assertSame(
            ['password' => ['code' => 'accounts.passwordTooShort', 'params' => ['min' => 10]]],
            InvalidInput::field('password', 'accounts.passwordTooShort', ['min' => 10])->fieldErrors,
        );
    }

    public function testTooLongNamesItsLimit(): void
    {
        $invalid = InvalidInput::tooLong('name', max: 120);

        self::assertSame(
            ['name' => ['code' => 'validation.tooLong', 'params' => ['max' => 120]]],
            $invalid->fieldErrors,
        );

        self::assertSame('checkFields', $invalid->getMessage());
    }

    public function testNestingPrefixesEveryField(): void
    {
        $invalid = new InvalidInput([
            'personId' => ['code' => 'accounts.personNotFound'],
            'guardianConsentNote' => ['code' => 'validation.tooLong', 'params' => ['max' => 200]],
        ]);

        self::assertSame(
            [
                'member.personId' => ['code' => 'accounts.personNotFound'],
                'member.guardianConsentNote' => ['code' => 'validation.tooLong', 'params' => ['max' => 200]],
            ],
            $invalid->nestedIn('member')->fieldErrors,
        );
    }
}
