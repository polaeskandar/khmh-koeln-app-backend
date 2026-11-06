<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\People\Application\Service\PersonRecords;
use App\Workflow\People\Model\PersonView;

final readonly class RenamePersonWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private PersonRecords $records,
    ) {
    }

    public function __invoke(string $id, ?string $firstName, ?string $lastName, ?string $displayName): PersonView
    {
        $this->access->require(Action::Update, Resource::Members, refusal: 'people.changeNotAllowed');

        return PersonView::of($this->records->rename($id, $firstName, $lastName, $displayName));
    }
}
