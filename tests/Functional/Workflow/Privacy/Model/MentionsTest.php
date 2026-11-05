<?php

declare(strict_types=1);

namespace App\Tests\Functional\Workflow\Privacy\Model;

use App\Context\Attendance\Application\Service\AttendanceErasures;
use App\Context\Learning\Application\Service\LearningErasures;
use App\Context\Meetings\Application\Service\MeetingErasures;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\PersonErasures;
use App\Context\People\Domain\Entity\Person;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use App\Tests\Support\Interface\Api\Privacy\ErasureFixture;
use App\Workflow\Privacy\Model\Mention;
use App\Workflow\Privacy\Model\Mentions;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MentionsTest extends KernelTestCase
{
    public function testANameMatchesAsAWholeWordInTheMembersGroupsOnly(): void
    {
        [$kiro] = new ErasureFixture(self::getContainer())->kiro();

        self::assertSame(
            [
                ['cancelReason',  'Kiro was there but not ticked.', '2026-11-01', 'Sunday class'],
                ['removalReason', 'Kiro ticked Mina by mistake',    '2026-10-25', 'Sunday class'],
                ['lessonNote',    'Ask Kiro to read.',              '2026-11-15', '3rd Class'],
            ],
            $this->mentionsOf($kiro),
        );
    }

    public function testAnArabicNameMatchesToo(): void
    {
        [, $service, $third] = new ErasureFixture(self::getContainer())->kiro();
        $kirollos = new Person('كيرلس', 'نشأت', null);
        new Database(self::getContainer())->persisted($kirollos);
        new Rosters(self::getContainer())->place($kirollos, $service, $third, '2026-09-01');
        $lesson = new Lessons(self::getContainer())->stored($third, null, '2026-11-22', 'Daniel');

        new Database(self::getContainer())
            ->connection()
            ->update('learning.lesson', ['notes' => 'كيرلس يقرأ الآية.'], ['id' => $lesson->id()->toRfc4122()]);

        self::assertSame([['lessonNote', 'كيرلس يقرأ الآية.', '2026-11-22', '3rd Class']], $this->mentionsOf($kirollos));
    }

    /**
     * @return list<list<string|null>>
     */
    private function mentionsOf(Person $person): array
    {
        $mentions = Mentions::of(
            self::service(PersonErasures::class)->erasable($person->id()),
            self::service(MeetingErasures::class),
            self::service(AttendanceErasures::class),
            self::service(LearningErasures::class),
            self::service(StructureLookup::class),
        );

        return array_map(static fn (Mention $mention): array => [
            $mention->where,
            $mention->text,
            $mention->date,
            $mention->group,
        ], $mentions);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private static function service(string $class): object
    {
        $service = self::getContainer()->get($class);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
