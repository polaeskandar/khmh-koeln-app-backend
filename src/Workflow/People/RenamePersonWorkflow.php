<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\People\Application\Service\PersonGroups;
use App\Context\People\Application\Service\PersonWrites;
use App\Library\Problem\Model\Ids;
use App\Workflow\People\Model\PersonView;

final readonly class RenamePersonWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private PersonWrites $records,
        private PersonGroups $groups,
    ) {
    }

    public function __invoke(string $id, ?string $firstName, ?string $lastName, ?string $displayName): PersonView
    {
        $scope = $this->access->requireScope(Action::Update, Resource::Members, refusal: 'people.changeNotAllowed');
        $personId = Ids::toUuid($id, notFound: 'person');
        $this->groups->requireVisible($personId, $scope);

        return PersonView::of($this->records->rename($personId, $firstName, $lastName, $displayName));
    }
}
