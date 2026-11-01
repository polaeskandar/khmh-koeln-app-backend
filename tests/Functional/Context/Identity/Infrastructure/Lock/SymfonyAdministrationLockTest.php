<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Identity\Infrastructure\Lock;

use App\Context\Identity\Infrastructure\Lock\SymfonyAdministrationLock;
use App\Tests\Support\Context\Identity\Infrastructure\Lock\OtherSession;
use App\Tests\Support\Database;
use Override;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SymfonyAdministrationLockTest extends KernelTestCase
{
    private ?OtherSession $otherSession = null;

    #[Override]
    protected function tearDown(): void
    {
        $this->otherSession?->close();
        parent::tearDown();
    }

    public function testAnotherSessionWaitsWhileTheWorkRuns(): void
    {
        $heldDuringWork = false;

        $this->lock()->whileLocked(function () use (&$heldDuringWork): void {
            $heldDuringWork = !$this->otherSession()->acquiresAdministrationLock();
        });

        self::assertTrue($heldDuringWork);
        self::assertTrue($this->otherSession()->acquiresAdministrationLock());
    }

    public function testTheLockIsReleasedWhenTheWorkFails(): void
    {
        try {
            $this->lock()->whileLocked(static function (): void {
                throw new RuntimeException('refused');
            });
        } catch (RuntimeException $failure) {
            self::assertSame('refused', $failure->getMessage());
        }

        self::assertTrue($this->otherSession()->acquiresAdministrationLock());
    }

    private function lock(): SymfonyAdministrationLock
    {
        return new SymfonyAdministrationLock(new Database(self::getContainer())->connection());
    }

    private function otherSession(): OtherSession
    {
        return $this->otherSession ??= new OtherSession(self::getContainer());
    }
}
