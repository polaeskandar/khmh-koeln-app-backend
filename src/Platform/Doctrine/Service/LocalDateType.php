<?php

declare(strict_types=1);

namespace App\Platform\Doctrine\Service;

use App\Library\Time\Model\LocalDate;
use App\Library\Types\Model\Type as Value;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;
use Override;

final class LocalDateType extends Type
{
    /**
     * @param array<string, mixed> $column
     */
    #[Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getDateTypeDeclarationSQL($column);
    }

    #[Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?LocalDate
    {
        $stored = Value::of($value)->toStringOrNull();

        if ($stored === null) {
            return null;
        }

        $instant = CarbonImmutable::createFromFormat('!' . $platform->getDateFormatString(), $stored, 'UTC');

        if ($instant === null) {
            throw InvalidFormat::new($stored, self::class, $platform->getDateFormatString());
        }

        return LocalDate::ofInstant($instant);
    }

    #[Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof LocalDate) {
            throw InvalidType::new($value, self::class, ['null', LocalDate::class]);
        }

        return $value->format($platform->getDateFormatString());
    }
}
