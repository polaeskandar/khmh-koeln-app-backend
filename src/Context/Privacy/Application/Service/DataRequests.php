<?php

declare(strict_types=1);

namespace App\Context\Privacy\Application\Service;

use App\Context\Privacy\Domain\Entity\DataRequest;
use App\Context\Privacy\Domain\Enum\RequestChannel;
use App\Context\Privacy\Domain\Enum\RequestKind;
use App\Context\Privacy\Domain\Repository\DataRequestRepositoryInterface;
use App\Context\Privacy\Domain\ValueObject\Receipt;
use App\Context\Privacy\Domain\ValueObject\ReceiptDraft;
use App\Context\Privacy\Domain\ValueObject\RequestDraft;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\NotFound;
use App\Library\Strings\Model\InputText;
use App\Library\Time\Client\Calendar;
use App\Library\Time\Model\LocalDate;
use App\Library\Time\Model\LocalTime;
use Symfony\Component\Uid\Uuid;

final readonly class DataRequests
{
    private const string NOON = '12:00';

    public function __construct(
        private DataRequestRepositoryInterface $requests,
        private Calendar $calendar,
    ) {
    }

    public function record(RequestDraft $draft): Uuid
    {
        $day = $this->receivedOn($draft->receipt);

        $request = new DataRequest(
            RequestKind::from($draft->kind),
            $draft->subject,
            $this->receiptOf($draft->receipt, $day),
            $this->calendar->monthAfter($day),
            InputText::of($draft->note)->trimmed()->toStringOrNull(),
        );

        $this->requests->save($request);

        return $request->id();
    }

    /**
     * @return list<DataRequest>
     */
    public function all(): array
    {
        return $this->requests->all();
    }

    public function get(string $id): DataRequest
    {
        $request = Uuid::isValid($id) ? $this->requests->find(Uuid::fromString($id)) : null;

        return $request ?? throw NotFound::of('request');
    }

    public function today(): string
    {
        return $this->calendar->today()->toString();
    }

    private function receivedOn(ReceiptDraft $receipt): LocalDate
    {
        $day = LocalDate::of($receipt->receivedOn);

        if ($this->calendar->today()->isBefore($day)) {
            throw InvalidInput::field('receipt.receivedOn', 'privacy.receivedInFuture');
        }

        return $day;
    }

    private function receiptOf(ReceiptDraft $receipt, LocalDate $day): Receipt
    {
        $receivedAt = $day->toString() === $this->calendar->today()->toString()
            ? $this->calendar->now()
            : $this->calendar->instantAt($day, LocalTime::of(self::NOON));

        return new Receipt(
            InputText::of($receipt->requestedBy)->collapsed()->toString(),
            RequestChannel::from($receipt->channel),
            $receivedAt->toDateTimeImmutable(),
        );
    }
}
