<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\People\Application\Service\PersonGroups;
use App\Context\People\Application\Service\PersonLifecycle;
use App\Context\People\Domain\Enum\PersonStatus;
use App\Library\Problem\Model\Ids;
use App\Workflow\People\Model\PersonView;

final readonly class DeactivatePersonWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private PersonLifecycle $lifecycle,
        private PersonGroups $groups,
    ) {
    }

    public function __invoke(string $personId): PersonView
    {
        $scope = $this->access->requireScope(Action::Update, Resource::Members, refusal: 'people.changeNotAllowed');
        $id = Ids::toUuid($personId, notFound: 'person');
        $this->groups->requireVisible($id, $scope);

        return PersonView::of($this->lifecycle->changeStatus($id, PersonStatus::Inactive));
    }
}
