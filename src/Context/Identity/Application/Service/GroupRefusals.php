<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Library\Problem\Exception\NotFound;
use App\Platform\Access\Model\Group;
use App\Platform\Access\Model\GroupScope;
use Symfony\Component\Uid\Uuid;

final readonly class GroupRefusals
{
    public static function requireIn(GroupScope $scope, Group $group, string $thing): void
    {
        if ($scope->covers($group)) {
            return;
        }

        throw NotFound::of($thing);
    }

    public static function requireReached(GroupScope $scope, Uuid $serviceId, string $thing): void
    {
        if ($scope->reaches($serviceId)) {
            return;
        }

        throw NotFound::of($thing);
    }
}
