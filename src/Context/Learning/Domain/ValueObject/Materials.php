<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\Text;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class Materials
{
    public const int MAX_ROWS = 10;

    /**
     * @param list<Material> $items
     */
    private function __construct(
        private array $items,
    ) {
    }

    /**
     * @param list<array<array-key, string|null>> $rows
     */
    public static function of(array $rows, string $field): self
    {
        $materials = Collections::mapPresent(new ArrayCollection($rows), Material::fromRow(...));

        if ($materials->count() > self::MAX_ROWS) {
            throw InvalidInput::field($field, 'materials.tooMany');
        }

        $errors = Collections::of(self::errorsOf($materials, $field))->toArray();

        return $errors === [] ? new self($materials->getValues()) : throw new InvalidInput($errors);
    }

    public static function none(): self
    {
        return new self([]);
    }

    /**
     * @return list<array{label: string, url: string|null}>
     */
    public function toArray(): array
    {
        return new ArrayCollection($this->items)->map(
            static fn (Material $material): array => $material->toArray(),
        )->getValues();
    }

    /**
     * @param ReadableCollection<int, Material> $materials
     *
     * @return iterable<string, array{code: string}>
     */
    private static function errorsOf(ReadableCollection $materials, string $field): iterable
    {
        foreach ($materials as $index => $material) {
            foreach (MaterialProblems::of($material) as $part => $code) {
                yield Text::format('%s.%d.%s', $field, $index, $part)->toString() => ['code' => $code];
            }
        }
    }
}
