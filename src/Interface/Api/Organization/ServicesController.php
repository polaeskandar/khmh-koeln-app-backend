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
    public function list(
        ListServicesWorkflow $listServices,
        #[MapQueryParameter]
        string $status = 'active',
    ): JsonResponse {
        return $this->json(ServiceListResponse::of($listServices($status)));
    }

    #[Route('/{id}', name: 'api_services_show', methods: ['GET'])]
    public function show(string $id, ShowServiceWorkflow $showService): JsonResponse
    {
        return $this->json(new ServiceResponse($showService($id)));
    }

    #[Route('', name: 'api_services_create', methods: ['POST'])]
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
    public function edit(
        string $id,
        #[MapRequestPayload]
        ServiceRequest $service,
        EditServiceWorkflow $editService,
    ): JsonResponse {
        return $this->json(new ServiceResponse($editService($id, $service->name ?? '', $service->description)));
    }
}
