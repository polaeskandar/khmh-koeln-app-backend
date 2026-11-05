<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Context\People\Domain\Entity\Person;
use App\Library\Collections\Model\Collections;
use App\Workflow\People\Model\DirectoryEntry;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class LinkedRecords
{
    /**
     * @param ReadableCollection<string, Person> $people
     * @param ReadableCollection<string, DirectoryEntry> $entries
     */
    private function __construct(
        private ReadableCollection $people,
        private ReadableCollection $entries,
    ) {
    }

    /**
     * @param ReadableCollection<int, Person> $people
     * @param list<DirectoryEntry> $entries the people's directory entries
     */
    public static function of(ReadableCollection $people, array $entries): self
    {
        return new self(
            Collections::firstPerKey($people, keyOf: self::personKey(...)),
            Collections::firstPerKey(new ArrayCollection($entries), keyOf: self::entryKey(...)),
        );
    }

    public function viewOf(Uuid $personId): MemberRecordView
    {
        $key = $personId->toRfc4122();

        return MemberRecordView::of($key, $this->people->get($key), $this->entries->get($key)->groups ?? []);
    }

    private static function personKey(Person $person): string
    {
        return $person->id()->toRfc4122();
    }

    private static function entryKey(DirectoryEntry $entry): string
    {
        return $entry->id;
    }
}
