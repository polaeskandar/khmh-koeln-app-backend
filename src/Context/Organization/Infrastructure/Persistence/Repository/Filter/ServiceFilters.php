<?php

declare(strict_types=1);

namespace App\Context\Organization\Infrastructure\Persistence\Repository\Filter;

use App\Context\Organization\Domain\Enum\ServiceFilter;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;

final readonly class ServiceFilters
{
    public static function criteria(ServiceFilter $filter): Criteria
    {
        $byName = Criteria::create()->orderBy(['name.key' => Order::Ascending]);

        return match ($filter) {
            ServiceFilter::Active => $byName->where(Criteria::expr()->isNull('archivedAt')),
            ServiceFilter::Archived => $byName->where(Criteria::expr()->neq('archivedAt', null)),
            ServiceFilter::All => $byName,
        };
    }
}
