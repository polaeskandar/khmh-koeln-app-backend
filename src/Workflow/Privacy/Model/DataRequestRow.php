<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Privacy\Domain\Entity\DataRequest;
use App\Workflow\Identity\Model\AccountReference;

final readonly class DataRequestRow
{
    public function __construct(
        public RequestHeader $header,
        public ?string $subjectName,
        public ?ErasedSubjectView $erasedSubject,
        public ReceiptView $receipt,
        public ClosingView $closing,
    ) {
    }

    public static function of(DataRequest $request, ?string $subjectName, ?AccountReference $closedBy): self
    {
        $receipt = $request->receipt();

        return new self(
            new RequestHeader(
                $request->id()->toRfc4122(),
                $request->kind()->value,
                $request->status()->value,
                $request->dueOn()->toString(),
                $request->note(),
            ),
            $subjectName,
            ErasedSubjectView::of($request->erasedSubject()),
            new ReceiptView($receipt->requestedBy, $receipt->channel->value, $receipt->receivedAt),
            new ClosingView(
                $request->closing()->at,
                $closedBy,
                $request->closing()->declineReason,
                $request->outcome(),
            ),
        );
    }
}
