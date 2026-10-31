<?php

declare(strict_types=1);

namespace App\Tests\Functional\Library\Time\Client;

use App\Library\Time\Client\Calendar;
use App\Library\Time\Model\LocalDate;
use App\Library\Time\Model\LocalTime;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\DateTimeTzImmutableType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

final class CalendarTest extends KernelTestCase
{
    public function testTheApplicationCalendarIsTheChurchCalendar(): void
    {
        $calendar = self::getContainer()->get(Calendar::class);
        self::assertInstanceOf(Calendar::class, $calendar);

        $instant = $calendar->instantAt(LocalDate::of('2026-11-08'), LocalTime::of('12:00'));

        self::assertSame('2026-11-08T12:00:00+01:00', $instant->toRfc3339String());
    }

    public function testCalendarValuesAreSentAsTheApiDocumentsThem(): void
    {
        $payload = [
            'date' => LocalDate::of('2026-11-08'),
            'time' => LocalTime::of('18:30'),
            'startsAt' => $this->instantFromTheDatabase('2026-11-08 11:00:00+00'),
        ];

        self::assertSame('{"date":"2026-11-08","time":"18:30","startsAt":"2026-11-08T12:00:00+01:00"}', $this->serializer()->serialize(
            $payload,
            'json',
        ));
    }

    private function instantFromTheDatabase(string $timestamp): ?DateTimeImmutable
    {
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $stored = $connection->fetchOne('SELECT CAST(? AS timestamptz)', [$timestamp]);

        return new DateTimeTzImmutableType()->convertToPHPValue($stored, $connection->getDatabasePlatform());
    }

    private function serializer(): SerializerInterface
    {
        $serializer = self::getContainer()->get(SerializerInterface::class);
        self::assertInstanceOf(SerializerInterface::class, $serializer);

        return $serializer;
    }
}
