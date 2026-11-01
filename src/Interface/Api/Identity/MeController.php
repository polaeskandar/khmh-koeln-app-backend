<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity;

use App\Interface\Application\Identity\UseCase\Query\ShowCurrentAccount\ShowCurrentAccountHandler;
use App\Interface\Application\Identity\UseCase\Query\ShowCurrentAccount\ShowCurrentAccountQuery;
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
        ShowCurrentAccountHandler $showCurrentAccount,
    ): JsonResponse {
        return $this->json($showCurrentAccount(new ShowCurrentAccountQuery($user->getUserIdentifier())));
    }
}
