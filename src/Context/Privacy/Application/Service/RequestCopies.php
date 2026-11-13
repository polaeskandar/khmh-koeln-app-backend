<?php

declare(strict_types=1);

namespace App\Context\Privacy\Application\Service;

use App\Context\Privacy\Domain\Entity\DataRequest;
use App\Context\Privacy\Domain\Repository\DataRequestRepositoryInterface;
use App\Context\Privacy\Domain\ValueObject\CopyableSubject;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Client\Calendar;
use Symfony\Component\Uid\Uuid;

final readonly class RequestCopies
{
    public function __construct(
        private DataRequestRepositoryInterface $requests,
        private DataRequestLifecycle $lifecycle,
        private Calendar $calendar,
    ) {
    }

    public function getSubjectOf(Uuid $requestId): CopyableSubject
    {
        return CopyableSubject::of($this->request($requestId));
    }

    public function complete(Uuid $requestId, Uuid $by): void
    {
        $this->requests->withWriteLock($requestId, function (DataRequest $request) use ($by): void {
            CopyableSubject::of($request);
            $this->lifecycle->complete($request, $this->calendar->now()->toDateTimeImmutable(), $by, outcome: null);
        });
    }

    private function request(Uuid $id): DataRequest
    {
        return $this->requests->find($id) ?? throw NotFound::of('request');
    }
}
