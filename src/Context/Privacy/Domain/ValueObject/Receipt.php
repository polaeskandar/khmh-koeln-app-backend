<?php

declare(strict_types=1);

namespace App\Context\Privacy\Domain\ValueObject;

use App\Context\Privacy\Domain\Enum\RequestChannel;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class Receipt
{
    public function __construct(
        #[ORM\Column(name: 'requested_by', length: 200)]
        public string $requestedBy,
        #[ORM\Column(name: 'channel', length: 9, enumType: RequestChannel::class)]
        public RequestChannel $channel,
        #[ORM\Column(name: 'received_at', type: Types::DATETIMETZ_IMMUTABLE)]
        public DateTimeImmutable $receivedAt,
    ) {
    }
}
