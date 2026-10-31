<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Identity\Infrastructure\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/testing/session', methods: ['GET'])]
final readonly class SessionProbeController
{
    public function __invoke(#[CurrentUser] UserInterface $user): JsonResponse
    {
        return new JsonResponse(['id' => $user->getUserIdentifier()]);
    }
}
