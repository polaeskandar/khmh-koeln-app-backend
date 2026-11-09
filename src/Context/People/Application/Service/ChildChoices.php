<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\PersonStatus;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\Text;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ChildChoices
{
    private const string FIELD = 'childIds';

    private const int MAX_CHILDREN = 20;

    public function __construct(
        private PersonRepositoryInterface $people,
    ) {
    }

    /**
     * @param list<string> $childIds
     *
     * @return ReadableCollection<int, Person>
     */
    public function chosen(array $childIds): ReadableCollection
    {
        $ids = Collections::firstPerKey(
            new ArrayCollection($childIds),
            keyOf: static fn (string $id): string => Text::of($id)->lower()->toString(),
        );

        if ($ids->isEmpty()) {
            throw InvalidInput::field(self::FIELD, 'accounts.chooseChild');
        }

        if ($ids->count() > self::MAX_CHILDREN) {
            throw InvalidInput::field(self::FIELD, 'validation.tooMany', ['max' => self::MAX_CHILDREN]);
        }

        $children = $this->people
            ->findByIds(Collections::mapPresent($ids, self::uuidOf(...))->getValues())
            ->filter(static fn (Person $child): bool => $child->status() === PersonStatus::Active);

        return $children->count() === $ids->count()
            ? $children
            : throw InvalidInput::field(self::FIELD, 'accounts.childNotFound');
    }

    private static function uuidOf(string $id): ?Uuid
    {
        return Uuid::isValid($id) ? Uuid::fromString($id) : null;
    }
}
