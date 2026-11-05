<?php

declare(strict_types=1);

namespace App\Tests\Support\Interface\Api\Learning;

use App\Context\People\Domain\Entity\Person;
use App\Library\Types\Model\Type;
use App\Tests\Support\JsonBody;

final readonly class ProgressRows
{
    /**
     * @param list<array<array-key, mixed>> $rows
     *
     * @return list<string>
     */
    public static function lastNames(array $rows): array
    {
        return array_map(static fn (array $row): string => Type::of(
            JsonBody::asArray($row['person'] ?? null, 'person')['lastName'] ?? null,
        )->toString(), $rows);
    }

    /**
     * @param list<array<array-key, mixed>> $rows
     *
     * @return array<array-key, mixed>
     */
    public static function rowOf(array $rows, Person $person): array
    {
        $matching = array_filter(
            $rows,
            static fn (array $row): bool => (
                (JsonBody::asArray($row['person'] ?? null, 'person')['id'] ?? null) === $person->id()->toRfc4122()
            ),
        );

        return array_values($matching)[0] ?? [];
    }

    /**
     * @param array<array-key, mixed> $row
     */
    public static function reporterName(array $row): ?string
    {
        if (($row['completedBy'] ?? null) === null) {
            return null;
        }

        return Type::of(JsonBody::asArray($row['completedBy'], 'completedBy')['name'] ?? null)->toString();
    }
}
