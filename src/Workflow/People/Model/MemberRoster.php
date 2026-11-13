<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\ValueObject\Family;
use App\Context\Scheduling\Domain\Entity\Schedule;
use App\Library\Collections\Model\Collections;
use App\Library\Strings\Model\InputText;
use App\Library\Strings\Model\Text;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class MemberRoster
{
    /**
     * @param ReadableCollection<int, Membership> $memberships the current ones in open services and stages
     */
    private function __construct(
        public LocalDate $today,
        public ReadableCollection $memberships,
        public OpenGroups $groups,
    ) {
    }

    /**
     * @param ReadableCollection<int, Service> $services the record's services
     * @param ReadableCollection<int, Stage> $stages the record's stages
     */
    public static function of(Family $record, ReadableCollection $services, ReadableCollection $stages): self
    {
        $current = $record->currentMemberships();
        $groups = OpenGroups::of($services, $stages);

        return new self($record->today, $current->filter($groups->includes(...)), $groups);
    }

    /**
     * @return list<Uuid>
     */
    public static function serviceIdsOf(Family $record): array
    {
        return $record
            ->currentMemberships()
            ->map(static fn (Membership $held): Uuid => $held->serviceId())
            ->getValues();
    }

    /**
     * @return list<Uuid>
     */
    public static function stageIdsOf(Family $record): array
    {
        $stageOf = static fn (Membership $held): ?Uuid => $held->stageId();

        return Collections::mapPresent($record->currentMemberships(), $stageOf)->getValues();
    }

    /**
     * @return list<Membership> by service name, the service's own roster first, then by stage position
     */
    public function ordered(): array
    {
        return Collections::sortedBy($this->memberships, keyOf: $this->sortKeyOf(...));
    }

    /**
     * @return list<Uuid>
     */
    public function stageIds(): array
    {
        return Collections::mapPresent(
            $this->memberships,
            static fn (Membership $membership): ?Uuid => $membership->stageId(),
        )->getValues();
    }

    public function covers(Schedule $schedule): bool
    {
        return !$this->memberships
            ->filter(static fn (Membership $membership): bool => RosterSeats::meetsIn($membership, $schedule->roster()))
            ->isEmpty();
    }

    public function groupOf(Schedule $schedule): string
    {
        $roster = $schedule->roster();
        $stage = $this->groups->stage($roster->stageId);

        return $stage?->name()->value ?? $this->groups->service($roster->serviceId)->name()->value;
    }

    private function sortKeyOf(Membership $membership): string
    {
        $position = $this->groups->stage($membership->stageId())?->position() ?? -1;
        $name = InputText::of($this->groups->service($membership->serviceId())->name()->value)->lowered()->toString();

        return Text::format('%s|%07d', $name, $position + 1)->toString();
    }
}
