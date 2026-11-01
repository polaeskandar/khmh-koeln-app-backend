<?php

declare(strict_types=1);

namespace App\Tests\Support\Interface\Application\Security\Service;

use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Interface\Application\Security\Model\RequiresAccess;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/testing/access', methods: ['GET'])]
final readonly class AccessProbeController
{
    #[RequiresAccess(Action::Read, Resource::Services)]
    #[RequiresAccess(Action::Update, Resource::Services)]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['granted' => true]);
    }
}
