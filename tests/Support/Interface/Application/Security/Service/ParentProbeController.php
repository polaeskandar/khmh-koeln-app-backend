<?php

declare(strict_types=1);

namespace App\Tests\Support\Interface\Application\Security\Service;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/parent/testing/probe', methods: ['GET'])]
final readonly class ParentProbeController
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['portal' => true]);
    }
}
