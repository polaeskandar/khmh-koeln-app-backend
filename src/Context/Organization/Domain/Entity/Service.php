<?php

declare(strict_types=1);

namespace App\Context\Organization\Domain\Entity;

use App\Context\Organization\Domain\ValueObject\Description;
use App\Context\Organization\Domain\ValueObject\ServiceName;
use App\Context\Organization\Domain\ValueObject\Tone;
use App\Library\Persistence\Mapping\TimestampedEntity;
use App\Library\Problem\Exception\InvalidInput;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use LogicException;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'service', schema: 'organization')]
#[ORM\UniqueConstraint(name: 'service_name_key_unique', columns: ['name_key'])]
final class Service extends TimestampedEntity
{
    private const int DESCRIPTION_LENGTH = 2000;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private readonly Uuid $id;

    #[ORM\Embedded(ServiceName::class, columnPrefix: false)]
    private ServiceName $name;

    #[ORM\Embedded(Description::class, columnPrefix: false)]
    private Description $description;

    #[ORM\Embedded(Tone::class, columnPrefix: false)]
    private ?Tone $tone = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $archivedAt = null;

    public function __construct(string $name, ?string $description)
    {
        $this->id = Uuid::v7();
        [$this->name, $this->description] = self::details($name, $description);
    }

    /**
     * @return array{ServiceName, Description}
     */
    public static function details(string $name, ?string $description): array
    {
        return InvalidInput::both(
            static fn (): ServiceName => ServiceName::of($name),
            static fn (): Description => Description::of($description, max: self::DESCRIPTION_LENGTH),
        );
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function name(): ServiceName
    {
        return $this->name;
    }

    public function description(): Description
    {
        return $this->description;
    }

    public function tone(): Tone
    {
        return $this->tone ?? throw new LogicException('A service gets its tone when it is first saved.');
    }

    public function takeTone(Tone $tone): void
    {
        if ($this->tone !== null) {
            throw new LogicException('A service keeps the tone it was first given.');
        }

        $this->tone = $tone;
    }

    public function edit(ServiceName $name, Description $description): void
    {
        $this->name = $name;
        $this->description = $description;
    }

    public function archivedAt(): ?DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function archive(DateTimeImmutable $at): void
    {
        $this->archivedAt = $at;
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    public function nameTaken(): InvalidInput
    {
        $code = $this->isArchived() ? 'services.nameTakenArchived' : 'services.nameTaken';

        return InvalidInput::field('name', $code, ['name' => $this->name->value]);
    }
}
