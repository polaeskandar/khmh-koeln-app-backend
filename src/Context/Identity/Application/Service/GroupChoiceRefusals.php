<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Library\Problem\Exception\InvalidInput;
use App\Platform\Access\Model\Group;
use App\Platform\Access\Model\GroupScope;

final readonly class GroupChoiceRefusals
{
    public static function requireChosen(GroupScope $scope, ?Group $group, string $fieldPrefix): void
    {
        $chosen = $group === null ? [] : [$group];

        if ($scope->covers(...$chosen)) {
            return;
        }

        throw $group !== null && $scope->reaches($group->serviceId)
            ? InvalidInput::field($fieldPrefix . 'stageId', 'meetings.chooseStage')
            : InvalidInput::field($fieldPrefix . 'serviceId', 'meetings.chooseService');
    }
}
