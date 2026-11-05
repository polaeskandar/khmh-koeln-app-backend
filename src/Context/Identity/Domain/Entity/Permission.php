<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Entity;

use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use Override;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'permission', schema: 'identity')]
#[ORM\UniqueConstraint(name: 'permission_resource_action_unique', columns: ['resource', 'action'])]
final class Permission implements JsonSerializable
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 6, enumType: Action::class)]
    private readonly Action $action;

    #[ORM\Column(length: 20, enumType: Resource::class)]
    private readonly Resource $resource;

    public function __construct(Action $action, Resource $resource)
    {
        $this->id = Uuid::v7();
        $this->action = $action;
        $this->resource = $resource;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function action(): Action
    {
        return $this->action;
    }

    public function resource(): Resource
    {
        return $this->resource;
    }

    public function isFor(Action $action, Resource $resource): bool
    {
        return $this->action === $action && $this->resource === $resource;
    }

    /**
     * @return array{action: string, resource: string}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['action' => $this->action->value, 'resource' => $this->resource->value];
    }
}
