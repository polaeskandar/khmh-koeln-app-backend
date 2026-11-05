<?php

declare(strict_types=1);

namespace App\Context\People\Domain\Entity;

use App\Context\People\Domain\Enum\GuardianKind;
use App\Context\People\Domain\Enum\GuardianStatus;
use App\Library\Persistence\Mapping\CreatedAtEntity;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'guardian_relationship', schema: 'people')]
#[ORM\UniqueConstraint(
    name: 'guardian_relationship_current_unique',
    columns: ['user_id', 'person_id'],
    options: ['where' => "((status)::text <> 'revoked'::text)"],
)]
#[ORM\Index(name: 'guardian_relationship_person_idx', columns: ['person_id'])]
final class GuardianRelationship extends CreatedAtEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'user_id', type: UuidType::NAME)]
    private readonly Uuid $accountId;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'person_id', nullable: false, onDelete: 'CASCADE')]
    private readonly Person $child;

    #[ORM\Column(length: 8, enumType: GuardianKind::class, options: ['default' => 'parent'])]
    private readonly GuardianKind $relationship;

    #[ORM\Column(length: 8, enumType: GuardianStatus::class)]
    private GuardianStatus $status = GuardianStatus::Approved;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private readonly DateTimeImmutable $approvedAt;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private readonly ?Uuid $approvedBy;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $revokedAt = null;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private ?Uuid $revokedBy = null;

    private function __construct(
        Uuid $accountId,
        Person $child,
        GuardianKind $relationship,
        DateTimeImmutable $approvedAt,
        ?Uuid $approvedBy,
    ) {
        $this->id = Uuid::v7();
        $this->accountId = $accountId;
        $this->child = $child;
        $this->relationship = $relationship;
        $this->approvedAt = $approvedAt;
        $this->approvedBy = $approvedBy;
    }

    public static function approved(
        Uuid $accountId,
        Person $child,
        GuardianKind $relationship,
        DateTimeImmutable $at,
        ?Uuid $by,
    ): self {
        return new self($accountId, $child, $relationship, $at, $by);
    }

    public function revoke(DateTimeImmutable $at, ?Uuid $by): void
    {
        if ($this->status === GuardianStatus::Revoked) {
            return;
        }

        $this->status = GuardianStatus::Revoked;
        $this->revokedAt = $at;
        $this->revokedBy = $by;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function accountId(): Uuid
    {
        return $this->accountId;
    }

    public function child(): Person
    {
        return $this->child;
    }

    public function relationship(): GuardianKind
    {
        return $this->relationship;
    }

    public function isApproved(): bool
    {
        return $this->status === GuardianStatus::Approved;
    }

    public function approvedAt(): DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function approvedBy(): ?Uuid
    {
        return $this->approvedBy;
    }

    public function revokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function revokedBy(): ?Uuid
    {
        return $this->revokedBy;
    }
}
