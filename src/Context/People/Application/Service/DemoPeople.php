<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Context\People\Domain\ValueObject\PersonName;
use App\Context\People\Domain\ValueObject\Tenure;
use App\Library\Time\Client\Calendar;
use LogicException;
use Symfony\Component\Uid\Uuid;

final readonly class DemoPeople
{
    private const int MEMBERS_SINCE_DAYS = 56;

    private const array GROUPS = [
        'Kindergarten' => [0, 12, ['Sophia', 'Mansour']],
        '1st Class' => [1, 11, ['Mimi', 'Gerges']],
        '2nd Class' => [2, 10, ['Mark', 'Wassef']],
        '3rd Class' => [3, 14, ['Mina', 'Hanna']],
        '4th Class' => [4, 13, ['Maria', 'Saad']],
        '5th & 6th Class' => [5, 10, ['Peter', 'Botros']],
        'Youth Meeting' => [6, 24, ['Mina', 'Hanna']],
    ];

    private const array FIRST_NAMES = [
        'Abanoub',
        'Bishoy',
        'Carina',
        'David',
        'Demiana',
        'Ereny',
        'Fady',
        'George',
        'Habib',
        'Irini',
        'Jana',
        'Kyrillos',
        'Lydia',
        'Marina',
        'Nardine',
        'Philopateer',
        'Rafael',
        'Sandra',
        'Theresa',
        'Veronika',
        'Youssef',
        'Joel',
        'Karen',
        'Lukas',
    ];

    private const array LAST_NAMES = [
        'Abadir',
        'Bishara',
        'Daoud',
        'Ebeid',
        'Fahmy',
        'Girgis',
        'Iskander',
        'Labib',
        'Makram',
        'Nashed',
        'Rizk',
        'Shenouda',
        'Tawfik',
        'Wahba',
        'Youssef',
        'Zaki',
    ];

    public function __construct(
        private PersonRepositoryInterface $people,
        private Calendar $calendar,
    ) {
    }

    public function seed(string $group, string $serviceId, ?string $stageId): void
    {
        $service = Uuid::fromString($serviceId);
        $stage = $stageId === null ? null : Uuid::fromString($stageId);
        $since = $this->calendar->today()->addDays(-self::MEMBERS_SINCE_DAYS);

        foreach (self::membersOf($group) as $person) {
            $this->people->save($person, new Membership($person, $service, $stage, Tenure::from($since)));
        }
    }

    /**
     * @return list<Person>
     */
    private static function membersOf(string $group): array
    {
        [$variety, $size, [$firstName, $lastName]] = self::GROUPS[$group] ?? throw new LogicException(
            'The demo has no people for ' . $group . '.',
        );

        $members = [new Person(PersonName::of($firstName, $lastName, displayName: null))];

        for ($member = 1; $member < $size; $member++) {
            $members[] = self::memberOf($variety, $member);
        }

        return $members;
    }

    /**
     * @param non-negative-int $variety
     * @param positive-int $member
     */
    private static function memberOf(int $variety, int $member): Person
    {
        $first = self::FIRST_NAMES[(($variety * 7) + $member) % 24] ?? 'Anna';
        $last = self::LAST_NAMES[(($variety * 3) + ($member * 5)) % 16] ?? 'Hanna';

        return new Person(PersonName::of($first, $last, displayName: null));
    }
}
