<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Problem\Model;

use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Model\Ids;
use PHPUnit\Framework\TestCase;

final class IdsTest extends TestCase
{
    private const string ID = '0194b3a2-7c1e-7d4f-9a2b-3c4d5e6f7a8b';

    public function testAnRfc4122IdBecomesItsUuid(): void
    {
        self::assertSame(self::ID, Ids::toUuid(self::ID, notFound: 'stage')->toRfc4122());
        self::assertSame(self::ID, Ids::toUuidOrNull(self::ID)?->toRfc4122());
    }

    public function testAMalformedIdIsTheThingNotFound(): void
    {
        try {
            Ids::toUuid('not-a-uuid', notFound: 'stage');
            self::fail('A malformed id was accepted.');
        } catch (NotFound $notFound) {
            self::assertSame('notFound.stage', $notFound->getMessage());
        }
    }

    public function testOnlyTheRfc4122FormIsAnId(): void
    {
        self::assertNull(Ids::toUuidOrNull(''));
        self::assertNull(Ids::toUuidOrNull(null));
        self::assertNull(Ids::toUuidOrNull('1BkPBX6T19U8TUAjBTtgwH'));
        self::assertNull(Ids::toUuidOrNull('01JTSN4Z0Y7KPMS4P1R6KDHBKV'));
    }
}
