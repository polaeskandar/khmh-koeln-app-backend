<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\InputText;
use App\Library\Strings\Model\Text;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class PersonDirectory
{
    private const int MIN_LETTERS = 2;

    private const int MAX_LENGTH = 100;

    public function __construct(
        private PersonRepositoryInterface $people,
        private Calendar $calendar,
    ) {
    }

    /**
     * @param list<Uuid> $ids
     *
     * @return ReadableCollection<int, Person>
     */
    public function records(array $ids): ReadableCollection
    {
        return $this->people->findByIds($ids);
    }

    /**
     * @return ReadableCollection<int, Person>
     */
    public function search(string $text, int $limit): ReadableCollection
    {
        return $this->people->searchActive(self::wordsOf($text), $limit);
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
            ->filter(static fn (Membership $membership): bool => $membership->tenure()->includes($today));
    }

    /**
     * @return list<string>
     */
    private static function wordsOf(string $text): array
    {
        if (!Text::of($text)->isUtf8()) {
            throw InvalidInput::field('q', 'validation.invalidType');
        }

        if (InputText::of($text)->length() > self::MAX_LENGTH) {
            throw InvalidInput::tooLong('q', self::MAX_LENGTH);
        }

        $folded = InputText::of($text)->folded()->toString();

        if (
            InputText::of(Text::of($folded)->replaceMatches('/\s+/u', replacement: '')->toString())->length()
            < self::MIN_LETTERS
        ) {
            throw InvalidInput::field('q', 'people.searchTooShort', ['min' => self::MIN_LETTERS]);
        }

        return new ArrayCollection(Text::of($folded)->split(' '))
            ->filter(static fn (string $word): bool => $word !== '')
            ->getValues();
    }
}
