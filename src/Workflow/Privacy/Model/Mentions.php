<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Organization\Domain\Entity\Stage;
use App\Library\Collections\Model\Collections;
use App\Library\Collections\Model\Orderings;
use App\Platform\Privacy\Model\FoundNote;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class Mentions
{
    /**
     * @param list<FoundNote> $notes
     * @param ReadableCollection<int, Stage> $stages
     *
     * @return list<Mention>
     */
    public static function of(array $notes, ReadableCollection $stages): array
    {
        $ordered = Orderings::byKeys(new ArrayCollection($notes), keysOf: static fn (FoundNote $note): array => [
            $note->date,
            $note->ref->id,
        ]);

        return new ArrayCollection($ordered)->map(
            static fn (FoundNote $note): Mention => new Mention(
                $note->ref->id,
                $note->ref->where,
                $note->text,
                $note->date,
                $note->meeting ?? self::stageNameOf($stages, $note->stageId),
            ),
        )->getValues();
    }

    /**
     * @param list<FoundNote> $notes
     *
     * @return list<Uuid>
     */
    public static function stageIdsOf(array $notes): array
    {
        return Collections::mapPresent(
            new ArrayCollection($notes),
            static fn (FoundNote $note): ?Uuid => $note->stageId,
        )->getValues();
    }

    /**
     * @param ReadableCollection<int, Stage> $stages
     */
    private static function stageNameOf(ReadableCollection $stages, ?Uuid $stageId): ?string
    {
        $stage = $stages->filter(static fn (Stage $stage): bool => $stage->id()->equals($stageId))->getValues();

        return ($stage[0] ?? null)?->name()->value;
    }
}
