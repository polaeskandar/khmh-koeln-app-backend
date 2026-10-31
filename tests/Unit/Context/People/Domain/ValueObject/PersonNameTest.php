<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\People\Domain\ValueObject;

use App\Context\People\Domain\ValueObject\PersonName;
use App\Tests\Support\Library\Problem\Exception\ThrownFieldErrors;
use PHPUnit\Framework\TestCase;

final class PersonNameTest extends TestCase
{
    public function testNamesAreCleanedAndKeyed(): void
    {
        $name = PersonName::of('  Mina ', ' HANNA  ', '   ');

        self::assertSame(['Mina', 'HANNA', null, 'mina_hanna'], [
            $name->firstName,
            $name->lastName,
            $name->displayName,
            $name->key,
        ]);
    }

    public function testTheSearchTextIgnoresCaseAndAccents(): void
    {
        self::assertSame('monika hanna kiki', PersonName::of('Mönika', 'Ḥanna', ' Kiki ')->searchText);
        self::assertSame('مينا حنا', PersonName::of('مِينَا', 'حَنَّا', null)->searchText);
    }

    public function testMissingNamesAreReported(): void
    {
        self::assertSame(
            ['firstName' => ['code' => 'people.firstNameRequired']],
            ThrownFieldErrors::of(static fn (): PersonName => PersonName::of(' ', 'Hanna', null)),
        );

        self::assertSame(
            ['lastName' => ['code' => 'people.lastNameRequired']],
            ThrownFieldErrors::of(static fn (): PersonName => PersonName::of('Mina', "\u{00A0}", null)),
        );
    }

    public function testNamesHaveTheirLimits(): void
    {
        self::assertSame(
            ['firstName' => ['code' => 'validation.tooLong', 'params' => ['max' => 80]]],
            ThrownFieldErrors::of(static fn (): PersonName => PersonName::of(
                str_repeat('a', times: 81),
                'Hanna',
                null,
            )),
        );

        self::assertSame(
            ['displayName' => ['code' => 'validation.tooLong', 'params' => ['max' => 120]]],
            ThrownFieldErrors::of(static fn (): PersonName => PersonName::of(
                'Mina',
                'Hanna',
                str_repeat('a', times: 121),
            )),
        );
    }
}
