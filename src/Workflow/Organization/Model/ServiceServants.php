<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Organization\Domain\Entity\StaffAssignment;
use App\Workflow\Identity\Model\AccountReference;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class ServiceServants
{
    /**
     * @param list<ServiceServant> $servants
     * @param list<AccountReference> $assignable
     */
    public function __construct(
        public array $servants,
        public array $assignable,
    ) {
    }

    /**
     * @param list<User> $servants by name
     * @param ReadableCollection<int, StaffAssignment> $assignments
     * @param list<User> $assignable by name
     */
    public static function of(array $servants, ReadableCollection $assignments, array $assignable): self
    {
        $rowsOf = static fn (User $servant): array => $assignments
            ->filter(static fn (StaffAssignment $assignment): bool => $assignment->accountId()->equals($servant->id()))
            ->map(static fn (StaffAssignment $assignment): ServiceServant => ServiceServant::of($assignment, $servant))
            ->getValues();

        return new self(
            new ArrayCollection($servants)->reduce(
                /**
                 * @param list<ServiceServant> $rows
                 *
                 * @return list<ServiceServant>
                 */
                static fn (array $rows, User $servant): array => [...$rows, ...$rowsOf($servant)],
                initial: [],
            ),
            new ArrayCollection($assignable)->map(AccountReference::of(...))->getValues(),
        );
    }
}
