<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\People\Application\Service\PersonGroups;
use App\Context\People\Application\Service\PersonRecords;
use App\Library\Problem\Model\Ids;
use App\Workflow\People\Model\PersonView;

final readonly class RenamePersonWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private PersonRecords $records,
        private PersonGroups $groups,
    ) {
    }

    public function __invoke(string $id, ?string $firstName, ?string $lastName, ?string $displayName): PersonView
    {
        $scope = $this->access->scope(Action::Update, Resource::Members, refusal: 'people.changeNotAllowed');
        $personId = Ids::toUuid($id, notFound: 'person');
        $this->groups->requireVisible($personId, $scope);

        return PersonView::of($this->records->rename($personId, $firstName, $lastName, $displayName));
    }
}
