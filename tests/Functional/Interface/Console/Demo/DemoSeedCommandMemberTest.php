<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Console\Demo;

use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

final class DemoSeedCommandMemberTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testNevineSeesHerTwoGroupsAndTheLettersToWrite(): void
    {
        self::mockTime('2026-11-07T10:00:00+01:00');
        $kernel = $this->browser()->getKernel();
        self::assertInstanceOf(KernelInterface::class, $kernel);
        new CommandTester(new Application($kernel)->find('app:demo:seed'))->execute([]);

        $this->signInAs('nevine.boulos@example.org', Type::of($_SERVER['DEMO_PASSWORD'] ?? null)->toString());
        $this->jsonRequest('GET', '/api/me');
        $this->assertJsonPath('member.groupCount', 2);
        $this->assertJsonPath('roles', []);
        $this->jsonRequest('GET', '/api/member/homework');

        $this->assertJsonPath('items.0.title', 'Write the first eight letters');
        $this->assertJsonPath('items.0.dueDate', '2026-11-09');
    }
}
