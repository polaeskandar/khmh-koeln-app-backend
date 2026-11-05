<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\PersonStatus;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class MemberRecords
{
    public function __construct(
        private PersonRepositoryInterface $people,
        private Calendar $calendar,
    ) {
    }

    /**
     * @return ReadableCollection<int, Membership>
     */
    public function currentMembershipsOf(Uuid $personId): ReadableCollection
    {
        $person = $this->people->find($personId);

        if ($person?->status() !== PersonStatus::Active) {
            return new ArrayCollection();
        }

        $today = $this->calendar->today();

        return $this->people
            ->membershipsOf(new ArrayCollection([$person]))
            ->filter(static fn (Membership $membership): bool => $membership->tenure()->includes($today));
    }

    /**
     * @param callable(Person): void $work
     */
    public function whileLinkable(string $personId, callable $work): void
    {
        $id = Uuid::isValid($personId) ? Uuid::fromString($personId) : throw self::notFound();

        $this->people->withShareLock($id, function () use ($id, $work): void {
            $person = $this->people->find($id);

            if ($person?->status() !== PersonStatus::Active) {
                throw self::notFound();
            }

            $work($person);
        });
    }

    private static function notFound(): InvalidInput
    {
        return InvalidInput::field('personId', 'accounts.personNotFound');
    }
}
