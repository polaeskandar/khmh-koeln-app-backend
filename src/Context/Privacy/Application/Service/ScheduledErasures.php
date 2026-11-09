<?php

declare(strict_types=1);

namespace App\Context\Privacy\Application\Service;

use App\Context\Privacy\Domain\Entity\DataRequest;
use App\Context\Privacy\Domain\Repository\DataRequestRepositoryInterface;
use App\Context\Privacy\Domain\ValueObject\ErasableSubject;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Client\Calendar;
use Symfony\Component\Uid\Uuid;

final readonly class ScheduledErasures
{
    private const string NO_VIEWER = '';

    public function __construct(
        private DataRequestRepositoryInterface $requests,
        private Calendar $calendar,
    ) {
    }

    public function withdraw(Uuid $accountId): void
    {
        $scheduled = $this->requests->openAppErasureOf($accountId);

        if ($scheduled?->deadlines()->erasesOn === null) {
            throw new RuleViolation('privacy.nothingScheduled');
        }

        $this->requests->withWriteLock($scheduled->id(), function (DataRequest $request): void {
            if (!$request->isOpen()) {
                throw new RuleViolation('privacy.nothingScheduled');
            }

            $request->withdraw($this->calendar->now()->toDateTimeImmutable());
        });
    }

    /**
     * @template T
     *
     * @param callable(ErasableSubject): T $work
     *
     * @return T|null
     */
    public function whileDue(Uuid $requestId, callable $work): mixed
    {
        return $this->requests->withWriteLock($requestId, static fn (DataRequest $request): mixed => $request->isOpen()
            ? $work(ErasableSubject::of($request, self::NO_VIEWER))
            : null);
    }

    /**
     * @param array<string, int> $outcome
     */
    public function complete(Uuid $requestId, array $outcome): void
    {
        $request = $this->requests->find($requestId) ?? throw NotFound::of('request');
        $request->complete($this->calendar->now()->toDateTimeImmutable(), by: null, outcome: $outcome);
        $this->requests->save($request);
    }
}
