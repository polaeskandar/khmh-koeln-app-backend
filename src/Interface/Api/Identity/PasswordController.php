<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity;

use App\Interface\Api\Identity\Request\ChangePasswordRequest;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Time\Client\Calendar;
use App\Workflow\Identity\ChangeOwnPasswordWorkflow;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class PasswordController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'limiter.password_change')]
        private readonly RateLimiterFactoryInterface $passwordChanges,
        private readonly Calendar $calendar,
    ) {
    }

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
        $accountId = $viewer->getUserIdentifier();
        $attempts = $this->passwordChanges->create($accountId);
        $left = $attempts->consume(0);

        if ($left->getRemainingTokens() === 0) {
            $seconds = $left->getRetryAfter()->getTimestamp() - $this->calendar->now()->getTimestamp();

            throw new TooManyRequestsHttpException($seconds, 'auth.tooManyAttempts');
        }

        if (!$changeOwnPassword($accountId, $request->currentPassword ?? '', $request->newPassword ?? '')) {
            $attempts->consume();

            throw InvalidInput::field('currentPassword', 'accounts.currentPasswordWrong');
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
