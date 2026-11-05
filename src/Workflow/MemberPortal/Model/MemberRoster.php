<?php

declare(strict_types=1);

namespace App\Workflow\MemberPortal\Model;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\MemberRecords;
use App\Context\People\Domain\Entity\Membership;
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

    public static function lookedUpIn(MemberRecords $records, StructureLookup $structure, Uuid $personId): self
    {
        $record = $records->ownRecord($personId);
        $current = $record->currentMemberships();
        $groups = OpenGroups::lookedUpIn($structure, $current);

        return new self($record->today, $current->filter($groups->includes(...)), $groups);
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
