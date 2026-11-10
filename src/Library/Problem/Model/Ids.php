<?php

declare(strict_types=1);

namespace App\Library\Problem\Model;

use App\Library\Problem\Exception\NotFound;
use Symfony\Component\Uid\Uuid;

final readonly class Ids
{
    public static function toUuid(string $id, string $notFound): Uuid
    {
        return self::toUuidOrNull($id) ?? throw NotFound::of($notFound);
    }

    public static function toUuidOrNull(?string $id): ?Uuid
    {
        return $id !== null && Uuid::isValid($id) ? Uuid::fromString($id) : null;
    }
}
