<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\People\Application\Service\Rosters;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class MemberCounts
{
    public function __construct(
        public int $members,
        public int $serviceLevel,
    ) {
    }

    public static function none(): self
    {
        return new self(members: 0, serviceLevel: 0);
    }

    public static function ofService(Service $service, Rosters $rosters): self
    {
        return self::in($rosters->serviceMembersToday([$service->id()]), $service);
    }

    /**
     * @param ReadableCollection<string, array{members: int, serviceLevel: int}> $counts
     */
    public static function in(ReadableCollection $counts, Service $service): self
    {
        $count = $counts->get($service->id()->toRfc4122());

        return $count === null ? self::none() : new self($count['members'], $count['serviceLevel']);
    }
}
