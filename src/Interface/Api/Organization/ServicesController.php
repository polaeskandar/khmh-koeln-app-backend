<?php

declare(strict_types=1);

namespace App\Interface\Api\Organization;

use App\Interface\Api\Organization\Request\ServiceRequest;
use App\Interface\Api\Organization\Response\ServiceListResponse;
use App\Interface\Api\Organization\Response\ServiceResponse;
use App\Workflow\Organization\CreateServiceWorkflow;
use App\Workflow\Organization\EditServiceWorkflow;
use App\Workflow\Organization\ListServicesWorkflow;
use App\Workflow\Organization\ShowServiceWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/services')]
final class ServicesController extends AbstractController
{
    #[Route('', name: 'api_services_list', methods: ['GET'])]
    #[OA\Get(summary: 'Services list.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: ServiceListResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function list(
        ListServicesWorkflow $listServices,
        #[MapQueryParameter]
        string $status = 'active',
    ): JsonResponse {
        return $this->json(ServiceListResponse::of($listServices($status)));
    }

    #[Route('/{id}', name: 'api_services_show', methods: ['GET'])]
    #[OA\Get(summary: 'Services show.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: ServiceResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function show(string $id, ShowServiceWorkflow $showService): JsonResponse
    {
        return $this->json(new ServiceResponse($showService($id)));
    }

    #[Route('', name: 'api_services_create', methods: ['POST'])]
    #[OA\Post(summary: 'Services create.')]
    #[OA\Response(
        response: 201,
        description: 'Created.',
        content: new OA\JsonContent(ref: new Model(type: ServiceResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function create(
        #[MapRequestPayload]
        ServiceRequest $service,
        CreateServiceWorkflow $createService,
    ): JsonResponse {
        $created = $createService($service->name ?? '', $service->description);

        return $this->json(
            new ServiceResponse($created),
            status: Response::HTTP_CREATED,
            headers: ['Location' => $this->generateUrl('api_services_show', ['id' => $created->service->id])],
        );
    }

    #[Route('/{id}', name: 'api_services_edit', methods: ['PUT'])]
    #[OA\Put(summary: 'Services edit.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: ServiceResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function edit(
        string $id,
        #[MapRequestPayload]
        ServiceRequest $service,
        EditServiceWorkflow $editService,
    ): JsonResponse {
        return $this->json(new ServiceResponse($editService($id, $service->name ?? '', $service->description)));
    }
}
