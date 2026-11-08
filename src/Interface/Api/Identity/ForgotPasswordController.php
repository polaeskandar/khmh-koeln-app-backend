<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity;

use App\Interface\Api\Identity\Request\ForgotPasswordRequest;
use App\Library\Strings\Model\InputText;
use App\Library\Time\Client\Calendar;
use App\Workflow\Identity\RequestPasswordResetWorkflow;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ForgotPasswordController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'limiter.forgot_password_client')]
        private readonly RateLimiterFactoryInterface $clients,
        #[Autowire(service: 'limiter.forgot_password_email')]
        private readonly RateLimiterFactoryInterface $emails,
        private readonly Calendar $calendar,
    ) {
    }

    #[Route('/api/auth/forgot-password', name: 'api_auth_forgot_password', methods: ['POST'])]
    #[OA\Post(security: [], summary: 'Auth forgot password.')]
    #[OA\Response(
        response: 202,
        description: 'Accepted.',
        content: new OA\JsonContent(required: ['status'], properties: [
            new OA\Property(property: 'status', type: 'string', enum: ['received']),
        ]),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function request(
        Request $request,
        #[MapRequestPayload]
        ForgotPasswordRequest $forgot,
        RequestPasswordResetWorkflow $requestPasswordReset,
    ): JsonResponse {
        $email = InputText::of($forgot->email)->trimmed()->lowered()->toString();
        $this->ensureAccepted($this->clients->create($request->getClientIp() ?? '')->consume());
        $this->ensureAccepted($this->emails->create($email)->consume());

        $requestPasswordReset($email);

        return $this->json(['status' => 'received'], status: Response::HTTP_ACCEPTED);
    }

    private function ensureAccepted(RateLimit $limit): void
    {
        if ($limit->isAccepted()) {
            return;
        }

        $seconds = $limit->getRetryAfter()->getTimestamp() - $this->calendar->now()->getTimestamp();

        throw new TooManyRequestsHttpException($seconds, 'auth.tooManyAttempts');
    }
}
