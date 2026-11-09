<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Entity;

use App\Library\Persistence\Mapping\CreatedAtEntity;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'password_token', schema: 'identity')]
#[ORM\UniqueConstraint(name: 'password_token_hash_unique', columns: ['token_hash'])]
#[ORM\Index(name: 'password_token_user_idx', columns: ['user_id'])]
final class PasswordToken extends CreatedAtEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private readonly Account $account;

    #[ORM\Column(name: 'token_hash', length: 64, options: ['fixed' => true])]
    private readonly string $tokenHash;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private readonly DateTimeImmutable $expiresAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $usedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'created_by', nullable: true, onDelete: 'SET NULL')]
    private readonly ?Account $createdBy;

    public function __construct(Account $account, string $tokenHash, DateTimeImmutable $expiresAt, ?Account $createdBy)
    {
        $this->id = Uuid::v7();
        $this->account = $account;
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
        $this->createdBy = $createdBy;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function account(): Account
    {
        return $this->account;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function usedAt(): ?DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function createdBy(): ?Account
    {
        return $this->createdBy;
    }
}
