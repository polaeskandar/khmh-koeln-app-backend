<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Application\Service\StageRosters;
use App\Context\People\Domain\ValueObject\RosterKey;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class AudienceSize
{
    public static function of(StageRosters $rosters, Stage $stage, LocalDate $today): int
    {
        $key = new RosterKey($stage->service()->id(), $stage->id(), $today);

        return new ArrayCollection($rosters->on([$key], alsoPersonIds: [])->activeMembersOf($key))->count();
    }
}
