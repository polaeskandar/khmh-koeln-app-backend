<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\PersonStatusFilter;
use App\Context\People\Domain\Repository\PersonListRepositoryInterface;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Context\People\Domain\ValueObject\CoveredRosters;
use App\Context\People\Domain\ValueObject\PersonFilter;
use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\InputText;
use App\Library\Strings\Model\Text;
use App\Library\Time\Client\Calendar;
use App\Platform\Access\Model\GroupScope;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class PersonLists
{
    public const int PAGE_SIZE = 25;

    private const int LAST_PAGE = 10_000;

    public function __construct(
        private PersonListRepositoryInterface $list,
        private PersonRepositoryInterface $people,
        private Calendar $calendar,
    ) {
    }

    public function buildFilter(
        PersonStatusFilter $status,
        ?Uuid $serviceId,
        ?Uuid $stageId,
        string $text,
    ): PersonFilter {
        $words = new ArrayCollection(Text::of(InputText::of($text)->folded()->toString())->split(' '));

        return new PersonFilter(
            status: $status->status(),
            serviceId: $serviceId,
            stageId: $stageId,
            words: $words->filter(static fn (string $word): bool => $word !== '')->getValues(),
            today: $this->calendar->today(),
        );
    }

    /**
     * @return ReadableCollection<int, Person>
     */
    public function listPage(PersonFilter $filter, int $page, GroupScope $scope): ReadableCollection
    {
        if ($page < 1) {
            throw InvalidInput::field('page', 'validation.tooSmall', ['min' => 1]);
        }

        if ($page > self::LAST_PAGE) {
            throw InvalidInput::field('page', 'validation.tooLarge', ['max' => self::LAST_PAGE]);
        }

        $ids = $this->list->page($filter, self::coveredBy($scope), ($page - 1) * self::PAGE_SIZE, self::PAGE_SIZE);

        $found = Collections::firstPerKey(
            $this->people->findByIds($ids),
            keyOf: static fn (Person $person): string => $person->id()->toRfc4122(),
        );

        return Collections::mapPresent(new ArrayCollection($ids), static fn (Uuid $id): ?Person => $found->get(
            $id->toRfc4122(),
        ));
    }

    public function count(PersonFilter $filter, GroupScope $scope): int
    {
        return $this->list->count($filter, self::coveredBy($scope));
    }

    private static function coveredBy(GroupScope $scope): CoveredRosters
    {
        return new CoveredRosters($scope->serviceIds(), $scope->stageIds());
    }
}
