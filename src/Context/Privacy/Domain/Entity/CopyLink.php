<?php

declare(strict_types=1);

namespace App\Context\Privacy\Domain\Entity;

use App\Library\Persistence\Mapping\CreatedAtEntity;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SensitiveParameter;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'copy_link', schema: 'privacy')]
#[ORM\UniqueConstraint(name: 'copy_link_hash_unique', columns: ['token_hash'])]
#[ORM\Index(name: 'copy_link_request_idx', columns: ['data_request_id'])]
final class CopyLink extends CreatedAtEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'data_request_id', nullable: false, onDelete: 'CASCADE')]
    private readonly DataRequest $request;

    #[ORM\Column(name: 'token_hash', length: 64, options: ['fixed' => true])]
    private readonly string $tokenHash;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private readonly DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'recipient_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $recipientId;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $usedAt = null;

    public function __construct(
        DataRequest $request,
        #[SensitiveParameter]
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        ?Uuid $recipientId,
    ) {
        $this->id = Uuid::v7();
        $this->request = $request;
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
        $this->recipientId = $recipientId;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function request(): DataRequest
    {
        return $this->request;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function recipientId(): ?Uuid
    {
        return $this->recipientId;
    }

    public function usedAt(): ?DateTimeImmutable
    {
        return $this->usedAt;
    }
}
