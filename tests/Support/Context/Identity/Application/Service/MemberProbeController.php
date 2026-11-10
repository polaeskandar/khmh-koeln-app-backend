<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Identity\Application\Service;

use App\Context\Identity\Application\Service\MemberCheck;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Uid\Uuid;

#[Route('/api/testing/member', methods: ['GET'])]
final readonly class MemberProbeController
{
    public function __construct(
        private MemberCheck $member,
    ) {
    }

    public function __invoke(#[CurrentUser] UserInterface $user): JsonResponse
    {
        return new JsonResponse([
            'personId' => $this->member->require(Uuid::fromString($user->getUserIdentifier()))->toRfc4122(),
        ]);
    }
}
