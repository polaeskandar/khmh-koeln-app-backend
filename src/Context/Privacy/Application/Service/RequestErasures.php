<?php

declare(strict_types=1);

namespace App\Context\Privacy\Application\Service;

use App\Context\Privacy\Domain\Entity\DataRequest;
use App\Context\Privacy\Domain\Repository\DataRequestRepositoryInterface;
use App\Context\Privacy\Domain\Repository\PersonTraceRepositoryInterface;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Client\Calendar;
use App\Platform\Doctrine\Contract\Transactions;
use Symfony\Component\Uid\Uuid;

final readonly class RequestErasures
{
    public function __construct(
        private DataRequestRepositoryInterface $requests,
        private PersonTraceRepositoryInterface $traces,
        private Transactions $transactions,
        private Calendar $calendar,
    ) {
    }

    /**
     * @template T of object
     *
     * @param callable(DataRequest): T $work
     *
     * @return T
     */
    public function whileErasable(string $requestId, callable $work): object
    {
        return $this->requests->withWriteLock(Uuid::fromString($requestId), static function (DataRequest $request) use (
            $work,
        ): object {
            $request->ensureErasable();

            return $work($request);
        });
    }

    /**
     * @template T of object
     *
     * @param callable(DataRequest): T $work
     *
     * @return T
     */
    public function rehearsing(string $requestId, callable $work): object
    {
        return $this->transactions->rehearse(fn (): object => $this->whileErasable($requestId, $work));
    }

    /**
     * @param array<string, int> $outcome
     */
    public function complete(string $requestId, string $by, array $outcome): void
    {
        $request = $this->requests->reloaded(Uuid::fromString($requestId)) ?? throw NotFound::of('request');
        $request->complete($this->calendar->now()->toDateTimeImmutable(), Uuid::fromString($by), $outcome);
        $this->requests->save($request);
    }

    public function forgetPerson(Uuid $personId, ?string $group): int
    {
        return $this->traces->forget($personId, $group);
    }
}
