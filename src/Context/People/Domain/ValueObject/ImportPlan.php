<?php

declare(strict_types=1);

namespace App\Context\People\Domain\ValueObject;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;

final readonly class ImportPlan
{
    /**
     * @param list<ImportLine> $errors
     * @param list<ImportLine> $created
     * @param list<ImportLine> $skipped
     * @param list<array{Person, list<Membership>}> $people
     */
    public function __construct(
        public array $errors,
        public array $created,
        public array $skipped,
        public array $people,
    ) {
    }

    /**
     * @param list<ImportLine> $errors
     */
    public static function refused(array $errors): self
    {
        return new self($errors, [], [], []);
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return list<string>
     */
    public function serviceIds(): array
    {
        $ids = [];

        foreach ($this->people as [, $memberships]) {
            foreach ($memberships as $membership) {
                $ids[] = $membership->serviceId()->toRfc4122();
            }
        }

        return $ids;
    }
}
