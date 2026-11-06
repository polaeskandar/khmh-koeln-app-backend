<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity;

use App\Interface\Api\Identity\Request\ChangeLanguageRequest;
use App\Interface\Api\Identity\Response\MeResponse;
use App\Workflow\Identity\ChangeLanguageWorkflow;
use App\Workflow\Identity\ShowCurrentAccountWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
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
    #[OA\Get(summary: 'Me.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: MeResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function show(
        #[CurrentUser]
        UserInterface $user,
        ShowCurrentAccountWorkflow $showCurrentAccount,
    ): JsonResponse {
        return $this->json(MeResponse::of($showCurrentAccount($user->getUserIdentifier())));
    }

    #[Route('/language', name: 'api_me_language', methods: ['PUT'])]
    #[OA\Put(summary: 'Change language.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: MeResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function changeLanguage(
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
