<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\People\Domain\ValueObject\RosterBook;
use App\Context\People\Domain\ValueObject\RosterKey;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class AudienceSize
{
    public static function of(RosterBook $book, RosterKey $key): int
    {
        return new ArrayCollection($book->activeMembersOf($key))->count();
    }
}
