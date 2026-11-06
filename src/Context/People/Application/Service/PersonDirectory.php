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
use Doctrine\Common\Collections\ReadableCollection;

final readonly class PersonDirectory
{
    private const int MIN_LETTERS = 2;

    public function __construct(
        private PersonRepositoryInterface $people,
        private Calendar $calendar,
    ) {
    }

    /**
     * @return ReadableCollection<int, Person>
     */
    public function search(string $text, int $limit): ReadableCollection
    {
        $folded = InputText::of($text)->folded()->toString();
        $letters = Text::of($folded)->replaceMatches('/\s+/u', replacement: '')->toString();

        if (InputText::of($letters)->length() < self::MIN_LETTERS) {
            throw InvalidInput::field('q', 'people.searchTooShort', ['min' => self::MIN_LETTERS]);
        }

        return $this->people->searchActive(Text::of($folded)->split(' '), $limit);
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
}
