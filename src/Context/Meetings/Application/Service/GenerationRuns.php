<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Meetings\Domain\ValueObject\GenerationResult;
use App\Context\Meetings\Domain\ValueObject\GenerationRun;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Time\Client\Calendar;
use App\Library\Time\Exception\InvalidLocalDate;
use App\Library\Time\Model\LocalDate;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('session_generator')]
final readonly class GenerationRuns
{
    public function __construct(
        private SessionGenerator $generator,
        private GeneratorLockInterface $lock,
        private Calendar $calendar,
        private LoggerInterface $logger,
    ) {
    }

    public function dayOf(?string $date): LocalDate
    {
        $today = $this->calendar->today();

        try {
            $day = $date === null ? $today : LocalDate::of($date);
        } catch (InvalidLocalDate) {
            throw InvalidInput::field('date', 'validation.invalidDate');
        }

        return $today->isBefore($day) ? throw InvalidInput::field('date', 'meetings.generateNotAfterToday') : $day;
    }

    public function run(LocalDate $day, ArchivedStructure $archived): GenerationRun
    {
        $started = $this->calendar->now();
        $result = new GenerationResult();

        $ran = $this->lock->runUnlessHeld(function () use ($day, $archived, &$result): void {
            $result = $this->generator->generate($day, $archived);
        });

        $this->logger->info($ran ? 'Session generation ran.' : 'Session generation is already running.', [
            'today' => $day->toString(),
            'created' => $result->created,
            'existing' => $result->existing,
            'failed' => $result->failed,
            'durationMs' => (int) $started->diffInMilliseconds($this->calendar->now()),
        ]);

        return new GenerationRun(skipped: !$ran, result: $result);
    }
}
