<?php

declare(strict_types=1);

namespace App\Context\Organization\Infrastructure\Persistence\Repository\Filter;

use Doctrine\Common\Collections\Criteria;

final readonly class StageFilters
{
    public static function archived(): Criteria
    {
        return Criteria::create()->where(Criteria::expr()->neq('archivedAt', null));
    }
}
