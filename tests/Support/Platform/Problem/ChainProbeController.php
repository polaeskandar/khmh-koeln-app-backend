<?php

declare(strict_types=1);

namespace App\Tests\Support\Platform\Problem;

use App\Library\Problem\Exception\NotFound;
use LogicException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/test/problems')]
final readonly class ChainProbeController
{
    #[Route('/rethrown', methods: ['GET'])]
    public function rethrown(): never
    {
        throw new AccessDeniedHttpException('services.adminOnlyCreate', NotFound::of('service'));
    }

    #[Route('/unauthorized', methods: ['GET'])]
    public function unauthorized(): never
    {
        throw new UnauthorizedHttpException('Cookie');
    }

    #[Route('/crash-chain', methods: ['GET'])]
    public function crashChain(): never
    {
        $cause = new LogicException('DETAIL: Key (email)=(mina@example.org) already exists.');

        throw new RuntimeException('Saving Mina Hanna failed', previous: $cause);
    }
}
