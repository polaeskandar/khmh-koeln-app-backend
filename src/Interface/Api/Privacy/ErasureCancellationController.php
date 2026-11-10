<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy;

use App\Interface\Api\Privacy\Request\CancelErasureRequest;
use App\Library\Strings\Model\InputText;
use App\Library\Strings\Model\Text;
use App\Library\Time\Client\Calendar;
use App\Workflow\Privacy\CancelOwnErasureWorkflow;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ErasureCancellationController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'limiter.sign_in_client')]
        private readonly RateLimiterFactoryInterface $clients,
        #[Autowire(service: 'limiter.sign_in_account')]
        private readonly RateLimiterFactoryInterface $accounts,
        private readonly Calendar $calendar,
    ) {
    }

    #[Route('/api/auth/erasure-cancellation', name: 'api_auth_erasure_cancellation', methods: ['POST'])]
    #[OA\Post(summary: 'Auth erasure cancellation.', security: [])]
    #[OA\Response(response: 204, description: 'Done; no body.')]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function cancel(
        Request $request,
        #[MapRequestPayload]
        CancelErasureRequest $cancellation,
        CancelOwnErasureWorkflow $cancelOwnErasure,
    ): Response {
        $email = InputText::of($cancellation->email)->trimmed()->lowered()->toString();
        $client = $request->getClientIp() ?? '';
        $this->ensureAccepted($this->clients->create($client)->consume());
        $this->ensureAccepted($this->accounts->create(Text::format('%s-%s', $email, $client)->toString())->consume());

        if (!$cancelOwnErasure($email, $cancellation->password ?? '')) {
            throw new UnauthorizedHttpException('Cookie', 'auth.invalidCredentials');
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
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
