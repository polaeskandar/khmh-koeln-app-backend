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
use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Application\Service\Rosters;
use App\Workflow\Organization\Model\MemberCounts;
use App\Workflow\Organization\Model\ServiceAbilities;
use App\Workflow\Organization\Model\ServiceInsights;
use App\Workflow\Organization\Model\ServiceList;
use App\Workflow\Organization\Model\ServiceOverview;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ListServicesWorkflow
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

    public function __invoke(string $status): ServiceList
    {
        $this->access->require(Action::Read, Resource::Services);

        return new ServiceList(
            items: $this->overviewsOf($this->services->listed($status)),
            canCreate: $this->access->allows(Action::Create, Resource::Services),
        );
    }

    /**
     * @param ReadableCollection<int, Service> $services
     *
     * @return list<ServiceOverview>
     */
    private function overviewsOf(ReadableCollection $services): array
    {
        $stages = $this->services->activeStagesIn($services);
        $can = ServiceAbilities::grantedBy($this->access);
        $insights = $this->insights();
        $members = $this->membersOf($services);

        return $services->map(static function (Service $service) use (
            $stages,
            $members,
            $can,
            $insights,
        ): ServiceOverview {
            $own = $stages->filter(static fn (Stage $stage): bool => $stage->service() === $service);

            return ServiceOverview::of(
                $service,
                $own,
                MemberCounts::in($members, $service),
                $can,
                $insights->of($service, $own),
            );
        })->getValues();
    }

    /**
     * @param ReadableCollection<int, Service> $services
     *
     * @return ReadableCollection<string, array{members: int, serviceLevel: int}>
     */
    private function membersOf(ReadableCollection $services): ReadableCollection
    {
        return $this->rosters->serviceMembersToday(
            $services->map(static fn (Service $service): Uuid => $service->id())->getValues(),
        );
    }

    private function insights(): ServiceInsights
    {
        return ServiceInsights::lookedUpIn(
            $this->accounts,
            $this->meetings,
            $this->rosters,
            $this->marks,
            ArchivedStructure::of($this->services->archivedIds()),
        );
    }
}
