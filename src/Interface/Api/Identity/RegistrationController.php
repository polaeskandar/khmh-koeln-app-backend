<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity;

use App\Interface\Api\Identity\Request\RegisterAccountRequest;
use App\Interface\Api\Identity\Response\RegistrationReceivedResponse;
use App\Interface\Api\Identity\Response\RegistrationServicesResponse;
use App\Library\Time\Client\Calendar;
use App\Workflow\Identity\ListRegistrationServicesWorkflow;
use App\Workflow\Identity\RegisterAccountWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth/register')]
final class RegistrationController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'limiter.registration')]
        private readonly RateLimiterFactoryInterface $registrations,
        private readonly Calendar $calendar,
    ) {
    }

    #[Route('', name: 'api_auth_register', methods: ['POST'])]
    #[OA\Post(summary: 'Auth register.', security: [])]
    #[OA\Response(
        response: 202,
        description: 'Accepted.',
        content: new OA\JsonContent(ref: new Model(type: RegistrationReceivedResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function register(
        Request $request,
        #[MapRequestPayload]
        RegisterAccountRequest $registration,
        RegisterAccountWorkflow $registerAccount,
    ): JsonResponse {
        $limit = $this->registrations->create($request->getClientIp() ?? '')->consume();

        if (!$limit->isAccepted()) {
            $seconds = $limit->getRetryAfter()->getTimestamp() - $this->calendar->now()->getTimestamp();

            throw new TooManyRequestsHttpException($seconds, 'auth.tooManyAttempts');
        }

        $registerAccount($registration->accountDetails(), $registration->registrationDetails());

        return $this->json(new RegistrationReceivedResponse(), status: Response::HTTP_ACCEPTED);
    }

    #[Route('/services', name: 'api_auth_register_services', methods: ['GET'])]
    #[OA\Get(summary: 'Auth register services.', security: [])]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: RegistrationServicesResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function services(ListRegistrationServicesWorkflow $listRegistrationServices): JsonResponse
    {
        return $this->json(RegistrationServicesResponse::of($listRegistrationServices()));
    }
}
