<?php

declare(strict_types=1);

namespace App\Interface\Api\Auth;

use App\Platform\Http\Contract\BearerTokens;
use App\Workflow\Identity\RevokeApiTokenWorkflow;
use LogicException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final readonly class TokenController
{
    public function __construct(
        private BearerTokens $bearerTokens,
    ) {
    }

    #[Route('/api/auth/tokens', name: 'api_auth_tokens', methods: ['POST'])]
    public function issue(): never
    {
        throw new BadRequestHttpException('invalidRequest');
    }

    #[Route('/api/auth/tokens/current', name: 'api_auth_tokens_current', methods: ['DELETE'])]
    public function revoke(Request $request, #[\SensitiveParameter] RevokeApiTokenWorkflow $revokeApiToken): Response
    {
        $token = $this->bearerTokens->of($request) ?? throw new LogicException(
            'The bearer firewall signed this request in.',
        );

        $revokeApiToken($token);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
