<?php

declare(strict_types=1);

namespace App\Platform\Access\Model;

use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\NotFound;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class GroupScope
{
    /**
     * @param ReadableCollection<int, Group>|null $groups
     */
    private function __construct(
        private ?ReadableCollection $groups,
    ) {
    }

    public static function churchWide(): self
    {
        return new self(null);
    }

    /**
     * @param list<Group> $groups
     */
    public static function assigned(array $groups): self
    {
        return new self(new ArrayCollection($groups));
    }

    public function isChurchWide(): bool
    {
        return $this->groups === null;
    }

    public function serviceWide(): self
    {
        return new self($this->groups?->filter(static fn (Group $group): bool => $group->isServiceWide()));
    }

    public function covers(Uuid $serviceId, ?Uuid $stageId): bool
    {
        $covering = $this->groups?->filter(static fn (Group $group): bool => $group->covers($serviceId, $stageId));

        return $covering?->isEmpty() !== true;
    }

    public function reaches(Uuid $serviceId): bool
    {
        $reaching = $this->groups?->filter(static fn (Group $group): bool => $group->serviceId->equals($serviceId));

        return $reaching?->isEmpty() !== true;
    }

    public function require(Uuid $serviceId, ?Uuid $stageId, string $thing): void
    {
        if ($this->covers($serviceId, $stageId)) {
            return;
        }

        throw NotFound::of($thing);
    }

    /**
     * @return list<string>|null the services covered with all their stages; null when the scope is church-wide
     */
    public function serviceIds(): ?array
    {
        return $this->groups
            ?->filter(static fn (Group $group): bool => $group->isServiceWide())
            ->map(static fn (Group $group): string => $group->serviceId->toRfc4122())
            ->getValues();
    }

    /**
     * @return list<string>|null the stages covered one by one; null when the scope is church-wide
     */
    public function stageIds(): ?array
    {
        return $this->groups === null
            ? null
            : Collections::mapPresent(
                $this->groups,
                static fn (Group $group): ?string => $group->stageId?->toRfc4122(),
            )->getValues();
    }
}
