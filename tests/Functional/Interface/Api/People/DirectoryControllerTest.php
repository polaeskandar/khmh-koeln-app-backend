<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\People;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Library\Time\Model\LocalDate;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use PHPUnit\Framework\Attributes\DataProvider;

final class DirectoryControllerTest extends ApiTestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('searches')]
    public function testEveryWordMatchesAName(string $q, array $expected): void
    {
        $this->people();
        $this->signInWithRoles('servant@example.org', 'servant');

        $this->jsonRequest('GET', '/api/people/directory?q=' . rawurlencode($q));

        self::assertResponseIsSuccessful();
        self::assertSame($expected, $this->lastNames());
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function searches(): iterable
    {
        yield 'display name' => ['kiro', ['Iskander']];
        yield 'two words' => ['mina han', ['Hanna', 'Hanna']];
        yield 'accents' => ['monika', ['Bishara']];
        yield 'harakat' => ['مِينَا', ['جرجس']];
        yield 'without harakat' => ['مينا', ['جرجس']];
        yield 'wildcards are literal' => ['a%', []];
    }

    public function testAnEntryHasOnlyNamesAndCurrentGroups(): void
    {
        $school = Structure::service('Sunday School');
        $third = Structure::stage($school, '3rd Class', position: 2);
        $youth = Structure::service('Youth');
        $kiro = new Person('Kirollos', 'Iskander', 'Kiro');
        $ended = new Membership($kiro, $youth->id(), null, LocalDate::of('2020-01-01'));
        $ended->endFrom(LocalDate::of('2021-01-01'));

        $this->database()->persisted(
            $school,
            $third,
            $youth,
            $kiro,
            new Membership($kiro, $school->id(), $third->id(), LocalDate::of('2025-09-01')),
            $ended,
        );

        $this->signInWithRoles('servant@example.org', 'servant');

        $this->jsonRequest('GET', '/api/people/directory?q=kiro');

        $entry = JsonBody::asArray($this->responseJson()['items'][0] ?? null, 'entry');
        self::assertSame(['id', 'firstName', 'lastName', 'displayName', 'groups'], array_keys($entry));
        $this->assertJsonPath('items.0.groups.0.service.name', 'Sunday School');
        $this->assertJsonPath('items.0.groups.0.stage.name', '3rd Class');
        self::assertCount(1, JsonBody::asArray($entry['groups'] ?? null, 'groups'));
    }

    public function testInactivePeopleAreLeftOutAndTwentyIsTheMost(): void
    {
        $people = array_map(
            static fn (int $n): Person => new Person('Mina', 'Person ' . $n, null),
            range(start: 1, end: 25),
        );

        $this->database()->persisted(...$people);

        $this
            ->database()
            ->connection()
            ->executeStatement("UPDATE people.person SET status = 'inactive' WHERE last_name = 'Person 1'");

        $this->signInWithRoles('servant@example.org', 'servant');

        $this->jsonRequest('GET', '/api/people/directory?q=mina');

        self::assertCount(20, $this->lastNames());
        self::assertNotContains('Person 1', $this->lastNames());
    }

    public function testTooShortAQueryParentsAndAnonymousCallersAreRefused(): void
    {
        $this->jsonRequest('GET', '/api/people/directory?q=mina');
        $this->assertProblem(401, 'auth.unauthenticated');

        $this->signInWithRoles('parent@example.org', 'parent');
        $this->jsonRequest('GET', '/api/people/directory?q=mina');
        $this->assertProblem(403, 'forbidden');

        $this->signInWithRoles('servant@example.org', 'servant');
        $this->jsonRequest('GET', '/api/people/directory?q=%20a%20');
        $this->assertProblem(422, 'checkFields');
        $this->assertFieldError('q', 'people.searchTooShort', ['min' => 2]);
    }

    private function people(): void
    {
        $this->database()->persisted(
            new Person('Kirollos', 'Iskander', 'Kiro'),
            new Person('Mina', 'Hanna', null),
            new Person('Mina', 'Hanna', null),
            new Person('Mina', 'Iskander', null),
            new Person('Mönika', 'Bishara', null),
            new Person('مِينَا', 'جرجس', null),
            new Person('Sara', 'Abadir', null),
        );
    }

    /**
     * @return list<string>
     */
    private function lastNames(): array
    {
        $items = JsonBody::asArray($this->responseJson()['items'] ?? null, 'items');

        return array_values(array_map(
            static fn (mixed $item): string => (string) (JsonBody::asArray($item, 'item')['lastName'] ?? ''),
            $items,
        ));
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
