<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity;

use App\Interface\Api\Identity\Response\MeResponse;
use App\Workflow\Identity\ShowCurrentAccountWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/me', methods: ['GET'])]
final class MeController extends AbstractController
{
    #[Route('', name: 'api_me')]
    public function show(
        #[CurrentUser]
        UserInterface $user,
        ShowCurrentAccountWorkflow $showCurrentAccount,
    ): JsonResponse {
        return $this->json(MeResponse::of($showCurrentAccount($user->getUserIdentifier())));
    }
}
