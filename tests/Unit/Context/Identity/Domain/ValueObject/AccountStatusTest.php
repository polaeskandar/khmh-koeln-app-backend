<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Identity\Domain\ValueObject;

use App\Context\Identity\Domain\ValueObject\AccountStatus;
use App\Library\Problem\Exception\RuleViolation;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AccountStatusTest extends TestCase
{
    public function testAnApprovedAccountIsActive(): void
    {
        self::assertTrue(
            AccountStatus::pending()->approvedOn(new DateTimeImmutable('2026-11-08T12:00:00+01:00'))->isActive(),
        );
    }

    public function testADisabledRequestIsNotApproved(): void
    {
        $at = new DateTimeImmutable('2026-11-08T12:00:00+01:00');

        $this->expectExceptionObject(new RuleViolation('accounts.requestDisabled'));

        AccountStatus::pending()->disabledAt($at)->approvedOn($at);
    }
}
