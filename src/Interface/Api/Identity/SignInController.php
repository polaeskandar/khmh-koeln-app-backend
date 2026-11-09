<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity;

use LogicException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[AsController]
#[Route('/api/auth', methods: ['POST'])]
final readonly class SignInController
{
    #[Route('/sign-in', name: 'api_auth_sign_in')]
    #[OA\Post(security: [], summary: 'Auth sign in.')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(required: ['email', 'password'], properties: [
            new OA\Property(property: 'email', type: 'string'),
            new OA\Property(property: 'password', type: 'string'),
        ]),
    )]
    #[OA\Response(response: 204, description: 'Done; no body.')]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function signIn(Request $request, #[CurrentUser] ?UserInterface $user): Response
    {
        if ($user === null) {
            throw new BadRequestHttpException('invalidRequest');
        }

        $request->getSession()->migrate(destroy: true);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/sign-out', name: 'api_auth_sign_out')]
    #[OA\Post(security: [], summary: 'Auth sign out.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object'))]
    #[OA\Response(response: 204, description: 'Done; no body.')]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function signOut(): never
    {
        throw new LogicException('The api firewall answers sign-out requests.');
    }
}
