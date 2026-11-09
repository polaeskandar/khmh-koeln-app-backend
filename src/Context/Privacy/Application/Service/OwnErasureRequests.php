<?php

declare(strict_types=1);

namespace App\Context\Privacy\Application\Service;

use App\Context\Privacy\Domain\Entity\DataRequest;
use App\Context\Privacy\Domain\Enum\RequestChannel;
use App\Context\Privacy\Domain\Enum\RequestKind;
use App\Context\Privacy\Domain\Repository\DataRequestRepositoryInterface;
use App\Context\Privacy\Domain\ValueObject\Deadlines;
use App\Context\Privacy\Domain\ValueObject\OpenErasure;
use App\Context\Privacy\Domain\ValueObject\Receipt;
use App\Context\Privacy\Domain\ValueObject\RequestSubject;
use App\Context\Privacy\Domain\ValueObject\ScheduledErasure;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Strings\Model\InputText;
use App\Library\Time\Client\Calendar;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Uid\Uuid;

final readonly class OwnErasureRequests
{
    private const int GRACE_DAYS = 14;

    public function __construct(
        private DataRequestRepositoryInterface $requests,
        private Calendar $calendar,
    ) {
    }

    public function openOf(Uuid $accountId): ?OpenErasure
    {
        $request = $this->requests->openAppErasureOf($accountId);

        return $request === null ? null : $this->viewOf($request);
    }

    public function schedule(Uuid $accountId, string $requestedBy, ?string $note): ScheduledErasure
    {
        if ($this->requests->openAppErasureOf($accountId) !== null) {
            throw new RuleViolation('privacy.alreadyRequested');
        }

        $today = $this->calendar->today();
        $erasesOn = $today->addDays(self::GRACE_DAYS);

        $request = new DataRequest(
            RequestKind::Erasure,
            new RequestSubject(null, $accountId),
            new Receipt($requestedBy, RequestChannel::App, $this->calendar->now()->toDateTimeImmutable()),
            new Deadlines($this->calendar->monthAfter($today), $erasesOn),
            InputText::of($note)->trimmed()->toStringOrNull(),
        );

        try {
            $this->requests->save($request);
        } catch (UniqueConstraintViolationException) {
            throw new RuleViolation('privacy.alreadyRequested');
        }

        return new ScheduledErasure($request->receipt()->receivedAt, $erasesOn->toString());
    }

    private function viewOf(DataRequest $request): OpenErasure
    {
        return new OpenErasure($request->receipt()->receivedAt, $request->deadlines()->dueOn->toString());
    }
}
