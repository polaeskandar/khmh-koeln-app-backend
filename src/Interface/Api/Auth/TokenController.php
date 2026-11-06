<?php

declare(strict_types=1);

namespace App\Interface\Api\Auth;

use App\Platform\Http\Contract\BearerTokens;
use App\Workflow\Identity\RevokeApiTokenWorkflow;
use LogicException;
use OpenApi\Attributes as OA;
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
    #[OA\Post(security: [], summary: 'Auth tokens.')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(required: ['email', 'password'], properties: [
            new OA\Property(property: 'email', type: 'string'),
            new OA\Property(property: 'password', type: 'string'),
            new OA\Property(property: 'deviceName', type: 'string', maxLength: 100),
        ]),
    )]
    #[OA\Response(
        response: 201,
        description: 'Created.',
        content: new OA\JsonContent(required: ['token', 'expiresAt'], properties: [
            new OA\Property(property: 'token', type: 'string'),
            new OA\Property(property: 'expiresAt', type: 'string'),
        ]),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function issue(): never
    {
        throw new BadRequestHttpException('invalidRequest');
    }

    #[Route('/api/auth/tokens/current', name: 'api_auth_tokens_current', methods: ['DELETE'])]
    #[OA\Delete(summary: 'Auth tokens current.')]
    #[OA\Response(response: 204, description: 'Done; no body.')]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function revoke(Request $request, #[\SensitiveParameter] RevokeApiTokenWorkflow $revokeApiToken): Response
    {
        $token = $this->bearerTokens->of($request) ?? throw new LogicException(
            'The bearer firewall signed this request in.',
        );

        $revokeApiToken($token);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
