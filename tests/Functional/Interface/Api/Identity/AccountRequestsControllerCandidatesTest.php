<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Identity;

use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Interface\Api\Identity\MemberFixture;
use App\Tests\Support\JsonBody;

final class AccountRequestsControllerCandidatesTest extends ApiTestCase
{
    public function testCandidatesShowTheirGroupsAndWhetherTheyHaveAnAccount(): void
    {
        new MemberFixture(self::getContainer())->people();
        $this->signInWithRoles('mariam@example.org', 'administrator');

        $this->jsonRequest('GET', '/api/account-requests/member-candidates?q=mina%20han');

        self::assertResponseIsSuccessful();

        self::assertSame(
            [
                ['Mina', '3rd Class',     false],
                ['Mina', 'Youth Meeting', false],
            ],
            $this->candidates(),
        );

        $this->assertJsonPath('items.1.groups.1.stage.name', 'Advanced');
        $this->jsonRequest('GET', '/api/account-requests/member-candidates?q=nevine');
        $this->assertJsonPath('items.0.hasAccount', true);
        $this->jsonRequest('GET', '/api/account-requests/member-candidates?q=n');
        $this->assertFieldError('q', 'people.searchTooShort', ['min' => 2]);

        $this->browser()->getCookieJar()->clear();
        $this->signInWithRoles('christine@example.org', 'service_leader');
        $this->jsonRequest('GET', '/api/account-requests/member-candidates?q=mina');
        $this->assertProblem(403, 'accounts.adminOnly');
    }

    /**
     * @return list<array{string, string, bool}>
     */
    private function candidates(): array
    {
        return array_values(array_map(
            static function (mixed $raw): array {
                $item = JsonBody::asArray($raw, 'item');
                $group = JsonBody::asArray(JsonBody::asArray($item['groups'] ?? null, 'groups')[0] ?? null, 'group');
                $key = \is_array($group['stage'] ?? null) ? 'stage' : 'service';
                $place = JsonBody::asArray($group[$key] ?? null, $key);

                return [
                    Type::of($item['firstName'] ?? null)->toString(),
                    Type::of($place['name'] ?? null)->toString(),
                    ($item['hasAccount'] ?? null) === true,
                ];
            },
            JsonBody::asArray($this->responseJson()['items'] ?? null, 'items'),
        ));
    }
}
