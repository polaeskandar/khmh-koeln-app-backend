<?php

declare(strict_types=1);

namespace App\Interface\Api\Auth;

use LogicException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[AsController]
#[Route('/api/auth', methods: ['POST'])]
final readonly class SessionController
{
    #[Route('/sign-in', name: 'api_auth_sign_in')]
    public function signIn(Request $request, #[CurrentUser] ?UserInterface $user): Response
    {
        if ($user === null) {
            throw new BadRequestHttpException('invalidRequest');
        }

        $request->getSession()->migrate(destroy: true);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/sign-out', name: 'api_auth_sign_out')]
    public function signOut(): never
    {
        throw new LogicException('The api firewall answers sign-out requests.');
    }
}
