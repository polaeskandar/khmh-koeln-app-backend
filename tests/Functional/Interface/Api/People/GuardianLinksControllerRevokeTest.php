<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\People;

use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Database;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class GuardianLinksControllerRevokeTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testARevokedLinkLeavesTheListAndThePortalAndCanBeGivenAgain(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $hoda = new SignInAccounts(self::getContainer())->add('hoda@example.org');

        $kiro = new Families(self::getContainer())
            ->child('Kiro')
            ->id()
            ->toRfc4122();

        $this->signInWithRoles('admin@example.org', 'administrator');
        $this->jsonRequest('POST', '/api/guardian-links', ['userId' => $hoda, 'personId' => $kiro]);
        $link = Type::of($this->responseJson()['id'] ?? null)->toString();

        $this->jsonRequest('POST', '/api/guardian-links/' . $link . '/revoke', []);
        self::assertResponseStatusCodeSame(204);
        self::mockTime('2026-11-08T11:00:00+01:00');
        $this->jsonRequest('POST', '/api/guardian-links/' . $link . '/revoke', []);
        self::assertResponseStatusCodeSame(204);

        self::assertSame('2026-11-08 10:00:00+01', new Database(self::getContainer())
            ->connection()
            ->fetchOne(
                "SELECT to_char(revoked_at AT TIME ZONE 'Europe/Berlin', 'YYYY-MM-DD HH24:MI:SS') || '+01' FROM people.guardian_relationship WHERE id = ?",
                [$link],
            ));

        $this->jsonRequest('GET', '/api/guardian-links');
        $this->assertJsonPath('items', []);
        $this->jsonRequest('POST', '/api/guardian-links/' . Uuid::v7()->toRfc4122() . '/revoke', []);
        $this->assertProblem(404, 'notFound.guardian');

        $this->browser()->getCookieJar()->clear();
        $this->signInAs('hoda@example.org', SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/parent/children/' . $kiro . '/homework');
        $this->assertProblem(404, 'notFound.person');

        $this->browser()->getCookieJar()->clear();
        $this->signInAs('admin@example.org', SignInAccounts::TYPED);
        $this->jsonRequest('POST', '/api/guardian-links', ['userId' => $hoda, 'personId' => $kiro]);
        self::assertResponseStatusCodeSame(201);
    }
}
