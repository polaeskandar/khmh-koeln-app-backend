<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity;

use App\Interface\Api\Identity\Request\ChangePasswordRequest;
use App\Workflow\Identity\ChangeOwnPasswordWorkflow;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class PasswordController extends AbstractController
{
    #[Route('/api/me/password', name: 'api_me_password', methods: ['POST'])]
    #[OA\Post(summary: 'Me password.')]
    #[OA\Response(response: 204, description: 'Done; no body.')]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function change(
        #[CurrentUser]
        UserInterface $viewer,
        #[MapRequestPayload]
        ChangePasswordRequest $request,
        #[\SensitiveParameter]
        ChangeOwnPasswordWorkflow $changeOwnPassword,
    ): Response {
        $changeOwnPassword($viewer->getUserIdentifier(), $request->currentPassword ?? '', $request->newPassword ?? '');

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
