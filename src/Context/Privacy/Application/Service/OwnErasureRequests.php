<?php

declare(strict_types=1);

namespace App\Context\Privacy\Application\Service;

use App\Context\Privacy\Domain\Entity\DataRequest;
use App\Context\Privacy\Domain\Enum\RequestChannel;
use App\Context\Privacy\Domain\Enum\RequestKind;
use App\Context\Privacy\Domain\Repository\DataRequestRepositoryInterface;
use App\Context\Privacy\Domain\ValueObject\OpenErasure;
use App\Context\Privacy\Domain\ValueObject\Receipt;
use App\Context\Privacy\Domain\ValueObject\RequestSubject;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Strings\Model\InputText;
use App\Library\Time\Client\Calendar;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Uid\Uuid;

final readonly class OwnErasureRequests
{
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

    public function request(Uuid $accountId, string $requestedBy, ?string $note): OpenErasure
    {
        if ($this->requests->openAppErasureOf($accountId) !== null) {
            throw new RuleViolation('privacy.alreadyRequested');
        }

        $request = new DataRequest(
            RequestKind::Erasure,
            new RequestSubject(null, $accountId),
            new Receipt($requestedBy, RequestChannel::App, $this->calendar->now()->toDateTimeImmutable()),
            $this->calendar->monthAfter($this->calendar->today()),
            InputText::of($note)->trimmed()->toStringOrNull(),
        );

        try {
            $this->requests->save($request);
        } catch (UniqueConstraintViolationException) {
            throw new RuleViolation('privacy.alreadyRequested');
        }

        return $this->viewOf($request);
    }

    private function viewOf(DataRequest $request): OpenErasure
    {
        return new OpenErasure($request->receipt()->receivedAt, $request->dueOn()->toString());
    }
}
