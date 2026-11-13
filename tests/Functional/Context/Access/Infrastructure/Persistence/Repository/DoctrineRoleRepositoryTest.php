<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Access\Infrastructure\Persistence\Repository;

use App\Context\Access\Infrastructure\Persistence\Repository\DoctrineRoleRepository;
use App\Tests\Support\Database;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineRoleRepositoryTest extends KernelTestCase
{
    public function testARoleIsFoundByItsNameWhateverTheSpacingAndCase(): void
    {
        $roles = new DoctrineRoleRepository(new Database(self::getContainer())->entityManager());

        self::assertSame('Service leader', $roles->findByName('  SERVICE   leader ')?->name()->value);
        self::assertNull($roles->findByName('Pastor'));
    }
}
