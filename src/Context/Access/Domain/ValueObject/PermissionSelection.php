<?php

declare(strict_types=1);

namespace App\Context\Access\Domain\ValueObject;

use App\Context\Access\Domain\Entity\Permission;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\InvalidInput;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class PermissionSelection
{
    private const string FIELD = 'permissions';

    /**
     * @param list<string> $keys
     */
    private function __construct(
        private array $keys,
    ) {
    }

    /**
     * @param list<array{action: string|null, resource: string|null}> $choices
     */
    public static function of(array $choices): self
    {
        $chosen = new ArrayCollection($choices);
        $keys = Collections::mapPresent($chosen, self::keyOf(...));
        $distinct = Collections::firstPerKey($keys, keyOf: static fn (string $key): string => $key);

        if ($distinct->count() !== $chosen->count()) {
            throw self::invalid();
        }

        return new self($distinct->getValues());
    }

    public function grants(Action $action, Resource $resource): bool
    {
        return new ArrayCollection($this->keys)->contains(self::key($action, $resource));
    }

    /**
     * @param ReadableCollection<int, Permission> $catalog
     *
     * @return list<Permission>
     */
    public function permissionsIn(ReadableCollection $catalog): array
    {
        $chosen = $catalog->filter(fn (Permission $permission): bool => $this->grants(
            $permission->action(),
            $permission->resource(),
        ));

        if ($chosen->count() !== new ArrayCollection($this->keys)->count()) {
            throw self::invalid();
        }

        return $chosen->getValues();
    }

    /**
     * @param array{action: string|null, resource: string|null} $choice
     */
    private static function keyOf(array $choice): ?string
    {
        $action = Action::tryFrom($choice['action'] ?? '');
        $resource = Resource::tryFrom($choice['resource'] ?? '');

        if ($action === null || $resource === null) {
            return null;
        }

        return self::key($action, $resource);
    }

    private static function key(Action $action, Resource $resource): string
    {
        return $resource->value . ':' . $action->value;
    }

    private static function invalid(): InvalidInput
    {
        return InvalidInput::field(self::FIELD, 'validation.invalidChoice');
    }
}
