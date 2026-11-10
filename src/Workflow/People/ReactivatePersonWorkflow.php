<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\People\Application\Service\PersonGroups;
use App\Context\People\Application\Service\PersonStatuses;
use App\Context\People\Domain\Enum\PersonStatus;
use App\Library\Problem\Model\Ids;
use App\Workflow\People\Model\PersonView;

final readonly class ReactivatePersonWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private PersonStatuses $statuses,
        private PersonGroups $groups,
    ) {
    }

    public function __invoke(string $personId): PersonView
    {
        $scope = $this->access->scope(Action::Update, Resource::Members, refusal: 'people.changeNotAllowed');
        $id = Ids::toUuid($personId, notFound: 'person');
        $this->groups->requireVisible($id, $scope);

        return PersonView::of($this->statuses->change($id, PersonStatus::Active));
    }
}
