<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\PersonStatus;
use App\Library\Strings\Model\Text;
use App\Workflow\People\Model\PersonGroup;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class MemberRecordView
{
    /**
     * @param list<string> $groups "Service" or "Service › Stage"
     */
    public function __construct(
        public string $id,
        public ?string $name,
        public bool $inactive,
        public array $groups,
    ) {
    }

    /**
     * @param list<PersonGroup> $groups
     */
    public static function of(string $personId, ?Person $person, array $groups): self
    {
        return new self(
            $personId,
            $person?->name()->fullName(),
            $person?->status() !== PersonStatus::Active,
            new ArrayCollection($groups)->map(self::groupName(...))->getValues(),
        );
    }

    private static function groupName(PersonGroup $group): string
    {
        $stage = $group->stage;

        if ($stage === null) {
            return $group->service->name;
        }

        return Text::format('%s › %s', $group->service->name, $stage->name)->toString();
    }
}
