<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\People\Application\Service\PersonStatuses;
use App\Context\People\Domain\Enum\PersonStatus;
use App\Workflow\People\Model\PersonView;

final readonly class DeactivatePersonWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private PersonStatuses $statuses,
    ) {
    }

    public function __invoke(string $personId): PersonView
    {
        $this->access->require(Action::Update, Resource::Members, refusal: 'people.changeNotAllowed');

        return PersonView::of($this->statuses->change($personId, PersonStatus::Inactive));
    }
}
