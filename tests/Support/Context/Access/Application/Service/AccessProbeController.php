<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Access\Application\Service;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/testing/access', methods: ['GET'])]
final readonly class AccessProbeController
{
    public function __construct(
        private AccessCheck $access,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $this->access->require(Action::Read, Resource::Services);
        $this->access->require(Action::Update, Resource::Services);

        return new JsonResponse(['granted' => true]);
    }
}
