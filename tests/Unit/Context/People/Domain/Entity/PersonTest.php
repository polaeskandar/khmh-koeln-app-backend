<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\People\Domain\Entity;

use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\PersonStatus;
use App\Context\People\Domain\ValueObject\Address;
use App\Context\People\Domain\ValueObject\BirthDate;
use App\Context\People\Domain\ValueObject\PersonalDetails;
use App\Library\Time\Model\LocalDate;
use App\Tests\Support\Library\Problem\Exception\ThrownFieldErrors;
use PHPUnit\Framework\TestCase;

final class PersonTest extends TestCase
{
    public function testANewPersonIsActiveWithoutPersonalDetails(): void
    {
        $person = new Person('  Mina ', ' HANNA  ', '   ');

        self::assertSame(PersonStatus::Active, $person->status());
        self::assertSame('mina_hanna', $person->name()->key);
        self::assertEquals(PersonalDetails::none(), $person->personalDetails());
    }

    public function testPersonalDetailsAreRecorded(): void
    {
        $person = new Person('Mina', 'Hanna', null);
        $today = LocalDate::of('2026-11-08');

        $person->recordPersonalDetails(
            new PersonalDetails(
                BirthDate::of(LocalDate::of('2015-03-01'), $today),
                Address::of('Domstraße 1', '50667', 'Köln'),
            ),
        );

        self::assertSame('2015-03-01', $person->personalDetails()->birthDate->value?->toString());
        self::assertSame('Köln', $person->personalDetails()->address->city);
    }

    public function testABirthDateInTheFutureOrAPartialAddressIsRejected(): void
    {
        self::assertSame(
            ['birthDate' => ['code' => 'validation.birthDateInFuture']],
            ThrownFieldErrors::of(static fn (): BirthDate => BirthDate::of(
                LocalDate::of('2026-11-09'),
                LocalDate::of('2026-11-08'),
            )),
        );

        self::assertSame(
            ['address' => ['code' => 'validation.addressIncomplete']],
            ThrownFieldErrors::of(static fn (): Address => Address::of('Domstraße 1', null, 'Köln')),
        );
    }
}
