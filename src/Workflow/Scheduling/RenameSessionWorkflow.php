<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Scheduling\Application\Service\CoveredSchedules;
use App\Context\Scheduling\Application\Service\SessionLookup;
use App\Context\Scheduling\Application\Service\SessionWrites;
use App\Library\Problem\Model\Ids;
use Symfony\Component\Uid\Uuid;

final readonly class RenameSessionWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private SessionWrites $details,
        private SessionLookup $sessions,
    ) {
    }

    public function __invoke(string $id, string $accountId, ?string $title, ?string $location): string
    {
        $this->access->require(Action::Update, Resource::Sessions);
        $session = $this->sessions->get(Ids::toUuid($id, notFound: 'session'));
        $group = CoveredSchedules::groupOfSession($session);
        $this->access->requireIn(Action::Update, Resource::Sessions, $group, missing: 'session');

        return $this->details->edit($session->id(), Uuid::fromString($accountId), $title, $location)->id()->toRfc4122();
    }
}
