<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity;

use App\Interface\Api\Identity\Request\SetPasswordFromLinkRequest;
use App\Library\Time\Client\Calendar;
use App\Workflow\Identity\SetPasswordFromLinkWorkflow;
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

#[Route('/api/auth/password-links')]
final class PasswordLinkController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'limiter.password_link')]
        private readonly RateLimiterFactoryInterface $passwordLinks,
        private readonly Calendar $calendar,
    ) {
    }

    #[Route('', name: 'api_auth_password_links', methods: ['POST'])]
    #[OA\Post(security: [], summary: 'Auth password links.')]
    #[OA\Response(response: 204, description: 'Done; no body.')]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function use(
        Request $request,
        #[MapRequestPayload]
        SetPasswordFromLinkRequest $link,
        SetPasswordFromLinkWorkflow $setPasswordFromLink,
    ): Response {
        $limit = $this->passwordLinks->create($request->getClientIp() ?? '')->consume();

        if (!$limit->isAccepted()) {
            $seconds = $limit->getRetryAfter()->getTimestamp() - $this->calendar->now()->getTimestamp();

            throw new TooManyRequestsHttpException($seconds, 'auth.tooManyAttempts');
        }

        if (!$setPasswordFromLink($link->token ?? '', $link->password ?? '')) {
            throw new UnprocessableEntityHttpException('auth.resetLinkInvalid');
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
