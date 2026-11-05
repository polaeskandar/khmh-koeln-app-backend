<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Console\Demo;

use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

final class DemoSeedCommandParentTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testHodaActsForMinaAndMimiOnlyAndNadiaWaits(): void
    {
        self::mockTime('2026-11-07T10:00:00+01:00');
        $kernel = $this->browser()->getKernel();
        self::assertInstanceOf(KernelInterface::class, $kernel);
        new CommandTester(new Application($kernel)->find('app:demo:seed'))->execute([]);
        $password = Type::of($_SERVER['DEMO_PASSWORD'] ?? null)->toString();

        $this->signInAs('hoda@example.org', $password);
        $this->jsonRequest('GET', '/api/me');
        $this->assertJsonPath('roles.0.name', 'Parent');
        $this->jsonRequest('GET', '/api/parent/children');

        self::assertSame(['Mimi Gerges', 'Mina Hanna'], $this->fullNames());

        $youthMina = new Database(self::getContainer())
            ->connection()
            ->fetchOne(
                'SELECT p.id::text FROM people.person p JOIN people.membership m ON m.person_id = p.id '
                . "WHERE p.first_name = 'Mina' AND m.stage_id IS NULL",
            );

        $this->jsonRequest('GET', '/api/parent/children/' . Type::of($youthMina)->toString() . '/homework');
        $this->assertProblem(404, 'notFound.person');

        $this->browser()->getCookieJar()->clear();
        $this->jsonRequest('POST', '/api/auth/sign-in', ['email' => 'nadia@example.org', 'password' => $password]);
        $this->assertProblem(403, 'auth.pending');
    }

    /**
     * @return list<string>
     */
    private function fullNames(): array
    {
        $names = array_map(
            static fn (mixed $item): string => Type::of(
                JsonBody::asArray($item, 'item')['fullName'] ?? null,
            )->toString(),
            JsonBody::asArray($this->responseJson()['items'] ?? null, 'items'),
        );

        sort($names);

        return $names;
    }
}
