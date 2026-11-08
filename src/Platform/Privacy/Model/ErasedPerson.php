<?php

declare(strict_types=1);

namespace App\Platform\Privacy\Model;

use Closure;
use Symfony\Component\Uid\Uuid;

final readonly class ErasedPerson
{
    /**
     * @param list<string> $names
     * @param list<ErasedPlace> $places
     * @param Closure(list<RosterSlot>): list<string> $rosteredOn
     */
    public function __construct(
        public Uuid $id,
        public array $names,
        public array $places,
        public ?string $group,
        private Closure $rosteredOn,
    ) {
    }

    /**
     * @param list<RosterSlot> $slots
     *
     * @return list<string>
     */
    public function rosteredOn(array $slots): array
    {
        return ($this->rosteredOn)($slots);
    }
}
