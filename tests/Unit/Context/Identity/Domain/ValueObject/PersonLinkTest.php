<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Identity\Domain\ValueObject;

use App\Context\Identity\Domain\Enum\AgeCheck;
use App\Context\Identity\Domain\ValueObject\PersonLink;
use App\Tests\Support\Library\Problem\Exception\ThrownFieldErrors;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class PersonLinkTest extends TestCase
{
    public function testABlankConsentNoteIsNoNote(): void
    {
        $link = PersonLink::to(Uuid::v7())->asMember(AgeCheck::GuardianConsent, '  ');

        self::assertSame(AgeCheck::GuardianConsent, $link->ageCheck);
        self::assertNull($link->guardianConsentNote);
    }

    public function testTheNoteIsTrimmedAndKeptOnlyWithAConsent(): void
    {
        self::assertSame(
            'Mother agreed after the liturgy.',
            PersonLink::to(Uuid::v7())->asMember(
                AgeCheck::GuardianConsent,
                ' Mother agreed after the liturgy. ',
            )->guardianConsentNote,
        );

        self::assertNull(PersonLink::to(Uuid::v7())->asMember(
            AgeCheck::SixteenOrOlder,
            'Not needed',
        )->guardianConsentNote);
    }

    public function testTheNoteHasALimit(): void
    {
        self::assertSame(
            ['guardianConsentNote' => ['code' => 'validation.tooLong', 'params' => ['max' => 200]]],
            ThrownFieldErrors::of(static fn (): PersonLink => PersonLink::to(
                Uuid::v7(),
            )->asMember(AgeCheck::GuardianConsent, str_repeat('a', times: 201))),
        );
    }

    public function testOnlyAnAccountWithAnAgeCheckIsAMember(): void
    {
        $none = PersonLink::to(Uuid::v7());

        self::assertNull($none->ageCheck);
        self::assertNull($none->guardianConsentNote);
        self::assertFalse($none->isMember());
        self::assertTrue($none->asMember(AgeCheck::SixteenOrOlder, null)->isMember());
    }
}
