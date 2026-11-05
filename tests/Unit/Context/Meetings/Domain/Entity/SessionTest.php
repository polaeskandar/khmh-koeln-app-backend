<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Meetings\Domain\Entity;

use App\Context\Meetings\Domain\Enum\SessionStatus;
use App\Context\Meetings\Domain\ValueObject\Stamp;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Model\LocalDate;
use App\Tests\Support\Context\Meetings\Domain\Entity\Meetings;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    public function testAScheduledSessionIsCanceledWithItsReason(): void
    {
        $session = Meetings::session(Meetings::schedule());

        self::assertTrue($session->cancel('Holiday'));

        self::assertSame(SessionStatus::Canceled, $session->progress()->status);
        self::assertSame('Holiday', $session->progress()->cancelReason);
    }

    /**
     * @param list<string> $steps
     */
    #[DataProvider('notScheduled')]
    public function testOnlyAScheduledSessionCanBeCanceled(array $steps): void
    {
        $session = Meetings::session(Meetings::schedule());
        $stamp = new Stamp(new DateTimeImmutable('2026-11-08T12:00:00+01:00'), by: null);

        foreach ($steps as $step) {
            match ($step) {
                'open' => $session->open(LocalDate::of('2026-11-08'), $stamp),
                'close' => $session->close($stamp),
                default => $session->cancel(reason: null),
            };
        }

        $this->expectExceptionObject(new RuleViolation('meetings.onlyScheduledCanCancel'));

        $session->cancel('Again');
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function notScheduled(): iterable
    {
        yield 'active' => [['open']];
        yield 'completed' => [['open', 'close']];
        yield 'canceled' => [['cancel']];
    }
}
