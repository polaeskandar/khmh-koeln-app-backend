<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\InvalidInput;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class HelperChoice
{
    private const int MAX_HELPERS = 20;

    private const string FIELD = 'details.collaboratorIds';

    /**
     * @param list<string> $sent the ids in canonical form
     * @param list<string> $preparers the sent ids that belong to lesson preparers
     */
    public function __construct(
        private array $sent,
        private array $preparers,
    ) {
    }

    /**
     * @param list<string> $current
     *
     * @return list<Uuid>
     */
    public function chosen(Uuid $responsible, array $current): array
    {
        $helpers = Collections::firstPerKey(
            new ArrayCollection($this->sent)->filter(static fn (string $id): bool => $id !== $responsible->toRfc4122()),
            keyOf: static fn (string $id): string => $id,
        );

        if ($helpers->count() > self::MAX_HELPERS) {
            throw InvalidInput::field(self::FIELD, 'validation.tooMany', ['max' => self::MAX_HELPERS]);
        }

        $known = new ArrayCollection([...$current, ...$this->preparers]);

        if (!$helpers->filter(static fn (string $id): bool => !$known->contains($id))->isEmpty()) {
            throw InvalidInput::field(self::FIELD, 'lessons.helpersInStage');
        }

        return $helpers->map(Uuid::fromString(...))->getValues();
    }
}
