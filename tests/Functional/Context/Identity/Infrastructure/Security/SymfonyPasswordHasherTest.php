<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Identity\Infrastructure\Security;

use App\Context\Identity\Domain\ValueObject\PlainPassword;
use App\Context\Identity\Infrastructure\Security\SecurityUser;
use App\Context\Identity\Infrastructure\Security\SymfonyPasswordHasher;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class SymfonyPasswordHasherTest extends KernelTestCase
{
    public function testAHashVerifiesOnlyItsPassword(): void
    {
        $hasher = $this->symfonyHasher();
        $hash = new SymfonyPasswordHasher($this->factory())->hash(PlainPassword::of('correct horse'));

        self::assertTrue($hasher->verify($hash, 'correct horse'));
        self::assertFalse($hasher->verify($hash, 'correct horse '));
    }

    private function symfonyHasher(): \Symfony\Component\PasswordHasher\PasswordHasherInterface
    {
        return $this->factory()->getPasswordHasher(SecurityUser::class);
    }

    private function factory(): PasswordHasherFactoryInterface
    {
        $factory = self::getContainer()->get(PasswordHasherFactoryInterface::class);
        self::assertInstanceOf(PasswordHasherFactoryInterface::class, $factory);

        return $factory;
    }
}
