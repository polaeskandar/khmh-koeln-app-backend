<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Problem\Exception;

use App\Library\Problem\Exception\InvalidInput;
use App\Tests\Support\Library\Problem\Exception\ThrownFieldErrors;
use PHPUnit\Framework\TestCase;
use stdClass;

final class InvalidInputTest extends TestCase
{
    public function testAFieldErrorCarriesItsCodeAndOnlyGivenParams(): void
    {
        self::assertSame(
            ['username' => ['code' => 'accounts.usernameTaken']],
            InvalidInput::field('username', 'accounts.usernameTaken')->fieldErrors,
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

    public function testAllReportsTheFieldErrorsOfEveryFailingCheckAtOnce(): void
    {
        self::assertSame(
            [
                'name' => ['code' => 'services.nameRequired'],
                'description' => ['code' => 'validation.tooLong', 'params' => ['max' => 2000]],
                'tone' => ['code' => 'validation.invalidChoice'],
            ],
            ThrownFieldErrors::of(static function (): void {
                InvalidInput::all(
                    static fn (): object => throw InvalidInput::field('name', 'services.nameRequired'),
                    static fn (): object => new stdClass(),
                    static fn (): object => throw InvalidInput::tooLong('description', max: 2000),
                    static fn (): object => throw InvalidInput::field('tone', 'validation.invalidChoice'),
                );
            }),
        );
    }

    public function testAllPassesWhenEveryCheckPasses(): void
    {
        $this->expectNotToPerformAssertions();

        InvalidInput::all(static fn (): object => new stdClass(), static fn (): object => new stdClass());
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
