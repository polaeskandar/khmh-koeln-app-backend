<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\PersonStatus;
use App\Context\People\Domain\Repository\PersonListRepositoryInterface;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Context\People\Domain\Repository\RosterRepositoryInterface;
use App\Context\People\Domain\ValueObject\RosterKey;
use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Strings\Model\Text;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class Attendees
{
    public function __construct(
        private PersonRepositoryInterface $people,
        private PersonListRepositoryInterface $list,
        private RosterRepositoryInterface $rosters,
        private Calendar $calendar,
    ) {
    }

    /**
     * @param list<RosterKey> $keys
     *
     * @return ReadableCollection<string, list<Uuid>>
     */
    public function rostersOn(array $keys): ReadableCollection
    {
        return $this->rosters->rostersOn($keys);
    }

    /**
     * @param list<Uuid> $ids
     *
     * @return ReadableCollection<string, Person>
     */
    public function peopleOf(array $ids): ReadableCollection
    {
        return Collections::firstPerKey($this->people->findByIds($ids), keyOf: self::keyOf(...));
    }

    /**
     * @param list<Uuid> $ids
     *
     * @return list<Uuid>
     */
    public function byName(array $ids): array
    {
        return $this->list->byName($ids);
    }

    /**
     * @param ReadableCollection<int, Person> $people
     *
     * @return ReadableCollection<int, Membership>
     */
    public function currentMembershipsOf(ReadableCollection $people): ReadableCollection
    {
        $today = $this->calendar->today();

        return $this->people
            ->membershipsOf($people)
            ->filter(static fn (Membership $membership): bool => $membership->isCurrentOn($today));
    }

    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function whileKept(string $personId, callable $work): mixed
    {
        return $this->people->withShareLock(Uuid::fromString($personId), $work);
    }

    public function find(string $personId): Person
    {
        return $this->people->find(Uuid::fromString($personId)) ?? throw NotFound::of('person');
    }

    public function markable(string $personId): Person
    {
        $person = $this->find($personId);

        if ($person->status() !== PersonStatus::Inactive) {
            return $person;
        }

        $name = $person->name();

        throw new RuleViolation('attendance.personInactive', [
            'name' => Text::format('%s %s', $name->firstName, $name->lastName)->toString(),
        ]);
    }

    private static function keyOf(Person $person): string
    {
        return $person->id()->toRfc4122();
    }
}
