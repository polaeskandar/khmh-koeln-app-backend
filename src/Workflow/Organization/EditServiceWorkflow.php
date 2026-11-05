<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Attendance\Application\Service\AttendanceMarks;
use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\ServiceMeetings;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Organization\Application\Service\ChurchServices;
use App\Context\People\Application\Service\Rosters;
use App\Workflow\Organization\Model\DetailChanges;
use App\Workflow\Organization\Model\MemberCounts;
use App\Workflow\Organization\Model\ServiceAbilities;
use App\Workflow\Organization\Model\ServiceInsights;
use App\Workflow\Organization\Model\ServiceOverview;

final readonly class EditServiceWorkflow
{
    // @mago-expect lint:excessive-parameter-list
    public function __construct(
        private AccessCheck $access,
        private AccountLookup $accounts,
        private ChurchServices $services,
        private Rosters $rosters,
        private ServiceMeetings $meetings,
        private AttendanceMarks $marks,
    ) {
    }

    public function __invoke(string $id, DetailChanges $changes): ServiceOverview
    {
        $this->access->require(Action::Update, Resource::Services);

        $service = $this->services->edit($id, $changes->name, $changes->descriptionChange());
        $stages = $this->services->activeStagesOf($service);

        $insights = ServiceInsights::lookedUpIn(
            $this->accounts,
            $this->meetings,
            $this->rosters,
            $this->marks,
            ArchivedStructure::of($this->services->archivedIds()),
        );

        return ServiceOverview::of(
            $service,
            $stages,
            MemberCounts::ofService($service, $this->rosters),
            ServiceAbilities::grantedBy($this->access),
            $insights->of($service, $stages),
        );
    }
}
