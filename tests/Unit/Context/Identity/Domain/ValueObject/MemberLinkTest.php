<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Identity\Domain\ValueObject;

use App\Context\Identity\Domain\Enum\AgeCheck;
use App\Context\Identity\Domain\ValueObject\MemberLink;
use App\Tests\Support\Library\Problem\Exception\ThrownFieldErrors;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class MemberLinkTest extends TestCase
{
    public function testABlankConsentNoteIsNoNote(): void
    {
        $link = MemberLink::of(Uuid::v7(), AgeCheck::GuardianConsent, '  ');

        self::assertSame(AgeCheck::GuardianConsent, $link->ageCheck);
        self::assertNull($link->guardianConsentNote);
    }

    public function testTheNoteIsTrimmedAndKeptOnlyWithAConsent(): void
    {
        $personId = Uuid::v7();

        self::assertSame(
            'Mother agreed after the liturgy.',
            MemberLink::of(
                $personId,
                AgeCheck::GuardianConsent,
                ' Mother agreed after the liturgy. ',
            )->guardianConsentNote,
        );

        self::assertNull(MemberLink::of($personId, AgeCheck::SixteenOrOlder, 'Not needed')->guardianConsentNote);
    }

    public function testTheNoteHasALimit(): void
    {
        self::assertSame(
            ['guardianConsentNote' => ['code' => 'validation.tooLong', 'params' => ['max' => 200]]],
            ThrownFieldErrors::of(static fn (): MemberLink => MemberLink::of(
                Uuid::v7(),
                AgeCheck::GuardianConsent,
                str_repeat('a', times: 201),
            )),
        );
    }

    public function testNoLinkClearsTheRecordTheAgeCheckAndTheNote(): void
    {
        $none = MemberLink::none();

        self::assertNull($none->personId);
        self::assertNull($none->ageCheck);
        self::assertNull($none->guardianConsentNote);
        self::assertFalse($none->isLinkedTo(Uuid::v7()));
    }
}
