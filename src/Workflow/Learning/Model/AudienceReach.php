<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\People\Domain\Entity\Person;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class AudienceReach
{
    public function __construct(
        public int $withoutParentAccount,
        public int $withoutAccount,
    ) {
    }

    /**
     * @param ReadableCollection<int, Person> $open the people of the stage without a report
     */
    public static function of(ReadableCollection $open, AudienceAccounts $accounts): self
    {
        return new self(
            withoutParentAccount: $open->filter(
                static fn (Person $person): bool => $accounts->parentsOf($person) === [],
            )->count(),
            withoutAccount: $open->filter(static fn (Person $person): bool => !$accounts->canBeReached(
                $person,
            ))->count(),
        );
    }
}
