<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\PersonStatus;
use App\Context\People\Domain\Repository\PersonListRepositoryInterface;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Context\People\Domain\ValueObject\PersonFilter;
use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\InputText;
use App\Library\Strings\Model\Text;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class PersonLists
{
    public const int PAGE_SIZE = 25;

    private const int LAST_PAGE = 10_000;

    private const string ALL = 'all';

    public function __construct(
        private PersonListRepositoryInterface $list,
        private PersonRepositoryInterface $people,
        private Calendar $calendar,
    ) {
    }

    public function filterOf(string $status, ?Uuid $serviceId, ?Uuid $stageId, string $text): PersonFilter
    {
        $words = new ArrayCollection(Text::of(InputText::of($text)->folded()->toString())->split(' '));

        return new PersonFilter(
            status: self::statusOf($status),
            serviceId: $serviceId,
            stageId: $stageId,
            words: $words->filter(static fn (string $word): bool => $word !== '')->getValues(),
            today: $this->calendar->today(),
        );
    }

    /**
     * @return ReadableCollection<int, Person>
     */
    public function page(PersonFilter $filter, int $page): ReadableCollection
    {
        if ($page < 1) {
            throw InvalidInput::field('page', 'validation.tooSmall', ['min' => 1]);
        }

        if ($page > self::LAST_PAGE) {
            throw InvalidInput::field('page', 'validation.tooLarge', ['max' => self::LAST_PAGE]);
        }

        $ids = $this->list->page($filter, ($page - 1) * self::PAGE_SIZE, self::PAGE_SIZE);

        $found = Collections::firstPerKey(
            $this->people->findByIds($ids),
            keyOf: static fn (Person $person): string => $person->id()->toRfc4122(),
        );

        return Collections::mapPresent(new ArrayCollection($ids), static fn (Uuid $id): ?Person => $found->get(
            $id->toRfc4122(),
        ));
    }

    public function count(PersonFilter $filter): int
    {
        return $this->list->count($filter);
    }

    /**
     * @param ReadableCollection<int, Person> $people
     *
     * @return ReadableCollection<int, Membership>
     */
    public function membershipsOf(ReadableCollection $people): ReadableCollection
    {
        return $this->people->membershipsOf($people);
    }

    private static function statusOf(string $status): ?PersonStatus
    {
        if ($status === self::ALL) {
            return null;
        }

        return PersonStatus::tryFrom($status) ?? throw InvalidInput::field('status', 'validation.invalidChoice');
    }
}
