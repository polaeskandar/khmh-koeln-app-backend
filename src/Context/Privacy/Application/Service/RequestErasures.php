<?php

declare(strict_types=1);

namespace App\Context\Privacy\Application\Service;

use App\Context\Privacy\Domain\Entity\DataRequest;
use App\Context\Privacy\Domain\Repository\DataRequestRepositoryInterface;
use App\Context\Privacy\Domain\ValueObject\ErasableSubject;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Client\Calendar;
use Symfony\Component\Uid\Uuid;

final readonly class RequestErasures
{
    public function __construct(
        private DataRequestRepositoryInterface $requests,
        private DataRequestLifecycle $lifecycle,
        private Calendar $calendar,
    ) {
    }

    /**
     * @template T
     *
     * @param callable(ErasableSubject): T $work
     *
     * @return T
     */
    public function whileErasable(Uuid $requestId, Uuid $viewerId, callable $work): mixed
    {
        $erasable = static fn (DataRequest $request): mixed => $work(ErasableSubject::of($request, $viewerId));

        return $this->requests->withWriteLock($requestId, $erasable);
    }

    /**
     * @param array<string, int> $outcome
     */
    public function complete(Uuid $requestId, Uuid $by, array $outcome): void
    {
        $request = $this->requests->find($requestId) ?? throw NotFound::of('request');

        $this->lifecycle->complete($request, $this->calendar->now()->toDateTimeImmutable(), $by, $outcome);
    }
}
