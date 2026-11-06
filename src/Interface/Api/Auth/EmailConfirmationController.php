<?php

declare(strict_types=1);

namespace App\Interface\Api\Auth;

use App\Interface\Api\Auth\Request\ConfirmEmailRequest;
use App\Library\Time\Client\Calendar;
use App\Workflow\Identity\ConfirmEmailWorkflow;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class EmailConfirmationController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'limiter.email_confirmation')]
        private readonly RateLimiterFactoryInterface $confirmations,
        private readonly Calendar $calendar,
    ) {
    }

    #[Route('/api/auth/email-confirmations', name: 'api_auth_email_confirmations', methods: ['POST'])]
    #[OA\Post(security: [], summary: 'Auth email confirmations.')]
    #[OA\Response(response: 204, description: 'Done; no body.')]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function confirm(
        Request $request,
        #[MapRequestPayload]
        ConfirmEmailRequest $confirmation,
        ConfirmEmailWorkflow $confirmEmail,
    ): Response {
        $limit = $this->confirmations->create($request->getClientIp() ?? '')->consume();

        if (!$limit->isAccepted()) {
            $seconds = $limit->getRetryAfter()->getTimestamp() - $this->calendar->now()->getTimestamp();

            throw new TooManyRequestsHttpException($seconds, 'auth.tooManyAttempts');
        }

        if (!$confirmEmail($confirmation->token ?? '')) {
            throw new UnprocessableEntityHttpException('auth.confirmationLinkInvalid');
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
