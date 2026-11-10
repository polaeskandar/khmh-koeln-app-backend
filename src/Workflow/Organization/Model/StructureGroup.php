<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\Organization\Domain\ValueObject\ChosenPlacement;
use App\Platform\Access\Model\Group;
use Symfony\Component\Uid\NilUuid;
use Symfony\Component\Uid\Uuid;

final readonly class StructureGroup
{
    public static function ofService(Service $service): Group
    {
        return new Group($service->id(), stageId: null);
    }

    public static function ofServiceId(string $serviceId): Group
    {
        return new Group(Uuid::isValid($serviceId) ? Uuid::fromString($serviceId) : new NilUuid(), stageId: null);
    }

    public static function ofStage(Stage $stage): Group
    {
        return new Group($stage->service()->id(), $stage->id());
    }

    public static function ofPlacement(ChosenPlacement $placement): Group
    {
        return new Group($placement->service->id(), $placement->stage?->id());
    }
}
