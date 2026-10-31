<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Identity\Domain\ValueObject;

use App\Context\Identity\Domain\ValueObject\Address;
use App\Tests\Support\Library\Problem\Exception\ThrownFieldErrors;
use PHPUnit\Framework\TestCase;

final class AddressTest extends TestCase
{
    public function testAllThreePartsAreCleaned(): void
    {
        $address = Address::of(' Domstraße   1 ', ' 50667', 'Köln ');

        self::assertSame('Domstraße 1', $address->street);
        self::assertSame('50667', $address->postalCode);
        self::assertSame('Köln', $address->city);
    }

    public function testNoPartsMeanNoAddress(): void
    {
        self::assertEquals(Address::none(), Address::of('  ', null, ''));
    }

    public function testOnlySomePartsAreIncomplete(): void
    {
        self::assertSame(
            ['address' => ['code' => 'validation.addressIncomplete']],
            ThrownFieldErrors::of(static fn (): Address => Address::of('Domstraße 1', '50667', '  ')),
        );
    }

    public function testEachPartHasItsLimit(): void
    {
        self::assertSame(
            ['postalCode' => ['code' => 'validation.tooLong', 'params' => ['max' => 10]]],
            ThrownFieldErrors::of(static fn (): Address => Address::of(
                'Domstraße 1',
                str_repeat('5', times: 11),
                'Köln',
            )),
        );

        self::assertSame(
            ['city' => ['code' => 'validation.tooLong', 'params' => ['max' => 80]]],
            ThrownFieldErrors::of(static fn (): Address => Address::of(
                'Domstraße 1',
                '50667',
                str_repeat('K', times: 81),
            )),
        );

        self::assertSame(
            ['street' => ['code' => 'validation.tooLong', 'params' => ['max' => 120]]],
            ThrownFieldErrors::of(static fn (): Address => Address::of(str_repeat('D', times: 121), '50667', 'Köln')),
        );
    }
}
