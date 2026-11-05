<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity;

use App\Interface\Api\Identity\Request\ChangeLanguageRequest;
use App\Interface\Api\Identity\Response\MeResponse;
use App\Workflow\Identity\ChangeLanguageWorkflow;
use App\Workflow\Identity\ShowCurrentAccountWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/me')]
final class MeController extends AbstractController
{
    #[Route('', name: 'api_me', methods: ['GET'])]
    public function show(
        #[CurrentUser]
        UserInterface $user,
        ShowCurrentAccountWorkflow $showCurrentAccount,
    ): JsonResponse {
        return $this->json(MeResponse::of($showCurrentAccount($user->getUserIdentifier())));
    }

    #[Route('', name: 'api_me_update', methods: ['PATCH'])]
    public function update(
        #[CurrentUser]
        UserInterface $user,
        #[MapRequestPayload]
        ChangeLanguageRequest $request,
        ChangeLanguageWorkflow $changeLanguage,
        ShowCurrentAccountWorkflow $showCurrentAccount,
    ): JsonResponse {
        $changeLanguage($user->getUserIdentifier(), $request->language ?? '');

        return $this->json(MeResponse::of($showCurrentAccount($user->getUserIdentifier())));
    }
}
