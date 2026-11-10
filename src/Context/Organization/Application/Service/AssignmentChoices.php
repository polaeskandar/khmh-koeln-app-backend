<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\ValueObject\ChosenPlacement;
use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\InvalidInput;
use Closure;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class AssignmentChoices
{
    private const int MAX_CHOSEN = 50;

    public function __construct(
        private PlacementChoices $placements,
    ) {
    }

    /**
     * @param list<array{serviceId: ?string, stageId: ?string}> $choices
     *
     * @return ReadableCollection<int, ChosenPlacement> each group once
     */
    public function chosen(array $choices, string $field): ReadableCollection
    {
        $listed = new ArrayCollection($choices);

        if ($listed->count() > self::MAX_CHOSEN) {
            throw InvalidInput::field($field, 'validation.tooMany', ['max' => self::MAX_CHOSEN]);
        }

        $pick =
            /** @param array{serviceId: ?string, stageId: ?string} $choice */
            fn (array $choice, int $index): ChosenPlacement => $this->placements->chosen(
                $choice['serviceId'],
                $choice['stageId'],
                fieldPrefix: $field . '.' . $index . '.',
            );

        InvalidInput::all(...Collections::mapArray(
            $choices,
            /** @param array{serviceId: ?string, stageId: ?string} $choice */
            static fn (array $choice, int $index): Closure => static fn (): ChosenPlacement => $pick($choice, $index),
        ));

        return new ArrayCollection(
            Collections::firstPerKey(
                new ArrayCollection(Collections::mapArray($choices, $pick)),
                keyOf: self::keyOf(...),
            )->getValues(),
        );
    }

    /**
     * @return non-empty-string
     */
    private static function keyOf(ChosenPlacement $group): string
    {
        return $group->service->id()->toRfc4122() . '/' . ($group->stage?->id()->toRfc4122() ?? '');
    }
}
