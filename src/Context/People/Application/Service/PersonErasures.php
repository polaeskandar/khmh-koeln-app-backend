<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Context\People\Domain\Repository\PersonTraceRepositoryInterface;
use App\Context\People\Domain\Repository\RosterRepositoryInterface;
use App\Context\People\Domain\ValueObject\ErasablePerson;
use App\Context\People\Domain\ValueObject\ErasedElsewhere;
use App\Context\People\Domain\ValueObject\PersonErased;
use App\Context\People\Domain\ValueObject\RosterKey;
use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\NotFound;
use App\Library\Strings\Model\WholeWord;
use App\Platform\Privacy\Contract\PersonNotes;
use App\Platform\Privacy\Contract\PersonReferences;
use App\Platform\Privacy\Model\ErasedPerson;
use App\Platform\Privacy\Model\ErasedPlace;
use App\Platform\Privacy\Model\FoundNote;
use App\Platform\Privacy\Model\NoteRef;
use App\Platform\Privacy\Model\RosterSlot;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Uid\Uuid;

final readonly class PersonErasures
{
    /**
     * @param iterable<int, PersonReferences> $references
     * @param iterable<int, PersonNotes> $notes
     */
    public function __construct(
        private PersonRepositoryInterface $people,
        private RosterRepositoryInterface $rosters,
        private PersonTraceRepositoryInterface $traces,
        #[AutowireIterator(PersonReferences::TAG)]
        private iterable $references,
        #[AutowireIterator(PersonNotes::NOTES_TAG)]
        private iterable $notes,
    ) {
    }

    /**
     * @param callable(ErasablePerson): ?string $groupOf
     */
    public function erase(string $personId, callable $groupOf): PersonErased
    {
        $id = Uuid::isValid($personId) ? Uuid::fromString($personId) : throw NotFound::of('person');
        $erasable = $this->erasable($id);
        $person = $this->erasedOf($erasable, $groupOf($erasable));

        $elsewhere = Collections::of($this->references)->map(
            static fn (PersonReferences $context): array => $context->forgetPerson($person),
        );

        return new PersonErased($this->traces->forget($id), ErasedElsewhere::of($elsewhere->getValues()));
    }

    /**
     * @return list<FoundNote>
     */
    public function mentionsOf(Uuid $personId): array
    {
        $person = $this->erasedOf($this->erasable($personId), group: null);

        $found = Collections::of($this->notes)->reduce(
            /**
             * @param list<FoundNote> $all
             *
             * @return list<FoundNote>
             */
            static fn (array $all, PersonNotes $context): array => [...$all, ...$context->notesAbout($person)],
            initial: [],
        );

        return new ArrayCollection($found)
            ->filter(static fn (FoundNote $note): bool => self::names($note, $person->names))
            ->getValues();
    }

    /**
     * @param list<FoundNote> $mentions
     */
    public function clearMentions(array $mentions): int
    {
        $refs = new ArrayCollection($mentions)->map(static fn (FoundNote $note): NoteRef => $note->ref)->getValues();

        foreach ($this->notes as $context) {
            $context->clearNotes($refs);
        }

        return new ArrayCollection($refs)->count();
    }

    public function guardianLinksOf(Uuid $accountId): int
    {
        return $this->traces->guardianLinksOf($accountId);
    }

    private function erasable(Uuid $personId): ErasablePerson
    {
        return $this->people->withWriteLock(
            $personId,
            fn (Person $person): ErasablePerson => new ErasablePerson(
                $person->id(),
                Collections::mapPresent(
                    new ArrayCollection([$person->name()->firstName, $person->name()->displayName]),
                    static fn (?string $named): ?string => $named,
                )->getValues(),
                $this->people->membershipsOf(new ArrayCollection([$person])),
            ),
        );
    }

    private function erasedOf(ErasablePerson $person, ?string $group): ErasedPerson
    {
        return new ErasedPerson(
            $person->id,
            $person->names,
            $person
                ->memberships
                ->map(
                    static fn (Membership $membership): ErasedPlace => new ErasedPlace(
                        $membership->serviceId(),
                        $membership->stageId(),
                    ),
                )
                ->getValues(),
            $group,
            fn (array $slots): array => $this->rosteredOn($person->id, $slots),
        );
    }

    /**
     * @param list<RosterSlot> $slots
     *
     * @return list<string>
     */
    private function rosteredOn(Uuid $personId, array $slots): array
    {
        $keys = Collections::firstPerKey(
            new ArrayCollection($slots),
            keyOf: static fn (RosterSlot $slot): string => $slot->id,
        )->map(static fn (RosterSlot $slot): RosterKey => new RosterKey($slot->serviceId, $slot->stageId, $slot->date));

        $rostered = new ArrayCollection(
            $this->rosters
                ->rostersOn($keys->getValues())
                ->filter(static fn (array $roster): bool => new ArrayCollection($roster)->map(
                    static fn (Uuid $member): string => $member->toRfc4122(),
                )->contains($personId->toRfc4122()))
                ->getKeys(),
        );

        return $keys->filter(static fn (RosterKey $key): bool => $rostered->contains($key->id()))->getKeys();
    }

    /**
     * @param list<string> $names
     */
    private static function names(FoundNote $note, array $names): bool
    {
        return !new ArrayCollection($names)
            ->filter(static fn (string $name): bool => WholeWord::in($note->text, $name))
            ->isEmpty();
    }
}
