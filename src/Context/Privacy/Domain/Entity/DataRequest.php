<?php

declare(strict_types=1);

namespace App\Context\Privacy\Domain\Entity;

use App\Context\Privacy\Domain\Enum\RequestKind;
use App\Context\Privacy\Domain\Enum\RequestStatus;
use App\Context\Privacy\Domain\ValueObject\Deadlines;
use App\Context\Privacy\Domain\ValueObject\ErasedSubject;
use App\Context\Privacy\Domain\ValueObject\Receipt;
use App\Context\Privacy\Domain\ValueObject\RequestClosing;
use App\Context\Privacy\Domain\ValueObject\RequestSubject;
use App\Library\Persistence\Mapping\CreatedAtEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'data_request', schema: 'privacy')]
#[ORM\Index(name: 'data_request_status_due_idx', columns: ['status', 'due_on'])]
#[ORM\UniqueConstraint(
    name: 'data_request_open_app_erasure_unique',
    columns: ['app_user_id'],
    options: ['where' => self::OPEN_APP_ERASURE],
)]
final class DataRequest extends CreatedAtEntity
{
    private const string ERASURE = "((kind)::text = 'erasure'::text)";

    private const string APP = "((channel)::text = 'app'::text)";

    private const string OPEN = "((status)::text = 'open'::text)";

    private const string OPEN_APP_ERASURE = '(' . self::ERASURE . ' AND ' . self::APP . ' AND ' . self::OPEN . ')';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 7, enumType: RequestKind::class)]
    private RequestKind $kind;

    #[ORM\Embedded(RequestSubject::class, columnPrefix: false)]
    private RequestSubject $subject;

    #[ORM\Embedded(Receipt::class, columnPrefix: false)]
    private Receipt $receipt;

    #[ORM\Embedded(Deadlines::class, columnPrefix: false)]
    private Deadlines $deadlines;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note;

    #[ORM\Column(length: 9, enumType: RequestStatus::class)]
    private RequestStatus $status;

    #[ORM\Embedded(RequestClosing::class, columnPrefix: false)]
    private RequestClosing $closing;

    /**
     * @var array<string, int>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    private ?array $outcome = null;

    #[ORM\Embedded(ErasedSubject::class, columnPrefix: false)]
    private ErasedSubject $erasedSubject;

    public function __construct(
        RequestKind $kind,
        RequestSubject $subject,
        Receipt $receipt,
        Deadlines $deadlines,
        ?string $note,
    ) {
        $this->id = Uuid::v7();
        $this->kind = $kind;
        $this->subject = $subject;
        $this->receipt = $receipt;
        $this->deadlines = $deadlines;
        $this->note = $note;
        $this->status = RequestStatus::Open;
        $this->closing = new RequestClosing();
        $this->erasedSubject = new ErasedSubject();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function kind(): RequestKind
    {
        return $this->kind;
    }

    public function subject(): RequestSubject
    {
        return $this->subject;
    }

    public function receipt(): Receipt
    {
        return $this->receipt;
    }

    public function deadlines(): Deadlines
    {
        return $this->deadlines;
    }

    public function note(): ?string
    {
        return $this->note;
    }

    public function status(): RequestStatus
    {
        return $this->status;
    }

    public function isOpen(): bool
    {
        return $this->status === RequestStatus::Open;
    }

    public function closing(): RequestClosing
    {
        return $this->closing;
    }

    /**
     * @return array<string, int>|null
     */
    public function outcome(): ?array
    {
        return $this->outcome;
    }

    public function erasedSubject(): ErasedSubject
    {
        return $this->erasedSubject;
    }

    public function changeStatus(RequestStatus $status): void
    {
        $this->status = $status;
    }

    public function changeClosing(RequestClosing $closing): void
    {
        $this->closing = $closing;
    }

    /**
     * @param array<string, int>|null $outcome
     */
    public function changeOutcome(?array $outcome): void
    {
        $this->outcome = $outcome;
    }
}
