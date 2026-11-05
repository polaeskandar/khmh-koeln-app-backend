<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Entity;

use App\Library\Persistence\Mapping\CreatedAtEntity;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SensitiveParameter;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'api_token', schema: 'identity')]
#[ORM\UniqueConstraint(name: 'api_token_hash_unique', columns: ['token_hash'])]
#[ORM\Index(name: 'api_token_user_idx', columns: ['user_id'])]
final class ApiToken extends CreatedAtEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private readonly Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private readonly User $user;

    #[ORM\Column(name: 'token_hash', length: 64, options: ['fixed' => true])]
    private readonly string $tokenHash;

    #[ORM\Column(name: 'device_name', length: 100, nullable: true)]
    private readonly ?string $deviceName;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private readonly DateTimeImmutable $expiresAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $revokedAt = null;

    public function __construct(
        User $user,
        #[SensitiveParameter]
        string $tokenHash,
        ?string $deviceName,
        DateTimeImmutable $expiresAt,
    ) {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->tokenHash = $tokenHash;
        $this->deviceName = $deviceName;
        $this->expiresAt = $expiresAt;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function user(): User
    {
        return $this->user;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function deviceName(): ?string
    {
        return $this->deviceName;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function lastUsedAt(): ?DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function revokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }
}
