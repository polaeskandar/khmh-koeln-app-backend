<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\People\Application\Service;

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

final class PersonErasuresTest extends KernelTestCase
{
    public function testANameMatchesAsAWholeWordInTheMembersGroupsOnly(): void
    {
        [$kiro] = new ErasureFixture(self::getContainer())->kiro();

        self::assertSame(
            [
                ['removalReason', 'Kiro ticked Mina by mistake',    '2026-10-25', 'Sunday class'],
                ['cancelReason',  'Kiro was there but not ticked.', '2026-11-01', 'Sunday class'],
                ['lessonNote',    'Ask Kiro to read.',              '2026-11-15', '3rd Class'],
            ],
            $this->mentionsOf($kiro),
        );
    }

    public function testAnArabicNameMatchesToo(): void
    {
        [, $service, $third] = new ErasureFixture(self::getContainer())->kiro();
        $rosters = new Rosters(self::getContainer());
        $kirollos = $rosters->person('كيرلس', 'نشأت');
        $rosters->place($kirollos, $service, $third, '2026-09-01');
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
        $erasures = self::getContainer()->get(PersonErasures::class);
        self::assertInstanceOf(PersonErasures::class, $erasures);
        $structure = self::getContainer()->get(StructureLookup::class);
        self::assertInstanceOf(StructureLookup::class, $structure);
        $found = $erasures->listMentionsOf($person->id());

        return array_map(
            static fn (Mention $mention): array => [
                $mention->where,
                $mention->text,
                $mention->date,
                $mention->group,
            ],
            Mentions::of($found, $structure->listStages(Mentions::stageIdsOf($found))),
        );
    }
}
