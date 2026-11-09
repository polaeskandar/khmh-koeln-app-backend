<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy\Response;

use App\Context\Privacy\Domain\Enum\RequestChannel;
use App\Context\Privacy\Domain\Enum\RequestKind;
use App\Context\Privacy\Domain\Enum\RequestStatus;
use App\Workflow\Identity\Model\AccountReference;
use App\Workflow\Privacy\Model\DataRequestRow;
use App\Workflow\Privacy\Model\ErasedSubjectView;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use Override;

final readonly class DataRequestRowResponse implements JsonSerializable
{
    public function __construct(
        private DataRequestRow $row,
    ) {
    }

    /**
     * @return array{
     *     id: string,
     *     kind: RequestKind,
     *     status: RequestStatus,
     *     subjectName: string|null,
     *     erasedSubject: ErasedSubjectView|null,
     *     requestedBy: string,
     *     channel: RequestChannel,
     *     receivedAt: DateTimeImmutable,
     *     dueOn: string,
     *     note: string|null,
     *     closedAt: DateTimeImmutable|null,
     *     closedBy: AccountReference|null,
     *     declineReason: string|null,
     *     outcome: array<string, int>|null,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $header = $this->row->header;
        $receipt = $this->row->receipt;
        $closing = $this->row->closing;

        return [
            'id' => $header->id,
            'kind' => $header->kind,
            'status' => $header->status,
            'subjectName' => $this->row->subjectName,
            'erasedSubject' => $this->row->erasedSubject,
            'requestedBy' => $receipt->requestedBy,
            'channel' => $receipt->channel,
            'receivedAt' => $receipt->receivedAt,
            'dueOn' => $header->dueOn,
            'note' => $header->note,
            'closedAt' => $closing->closedAt,
            'closedBy' => $closing->closedBy,
            'declineReason' => $closing->declineReason,
            'outcome' => $closing->outcome,
        ];
    }

    /**
     * @param list<DataRequestRow> $rows
     *
     * @return list<self>
     */
    public static function listOf(array $rows): array
    {
        return new ArrayCollection($rows)->map(static fn (DataRequestRow $row): self => new self($row))->getValues();
    }
}
