<?php

declare(strict_types=1);

namespace App\Interface\Api\Organization;

use App\Interface\Api\Organization\Request\CreateServiceRequest;
use App\Interface\Api\Organization\Request\EditServiceRequest;
use App\Interface\Api\Organization\Response\ServiceListResponse;
use App\Interface\Api\Organization\Response\ServiceResponse;
use App\Workflow\Organization\CreateServiceWorkflow;
use App\Workflow\Organization\EditServiceWorkflow;
use App\Workflow\Organization\ListServicesWorkflow;
use App\Workflow\Organization\Model\DetailChanges;
use App\Workflow\Organization\ShowServiceWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

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

    #[Route('/{id}', name: 'api_services_show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(string $id, ShowServiceWorkflow $showService): JsonResponse
    {
        return $this->json(new ServiceResponse($showService($id)));
    }

    #[Route('', name: 'api_services_create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload]
        CreateServiceRequest $service,
        CreateServiceWorkflow $createService,
    ): JsonResponse {
        $created = $createService($service->name ?? '', $service->description);

        return $this->json(
            new ServiceResponse($created),
            status: Response::HTTP_CREATED,
            headers: ['Location' => $this->generateUrl('api_services_show', ['id' => $created->service->id])],
        );
    }

    #[Route('/{id}', name: 'api_services_edit', requirements: ['id' => Requirement::UUID], methods: ['PATCH'])]
    public function edit(
        string $id,
        Request $request,
        #[MapRequestPayload]
        EditServiceRequest $changes,
        EditServiceWorkflow $editService,
    ): JsonResponse {
        return $this->json(new ServiceResponse($editService(
            $id,
            new DetailChanges(
                name: $changes->name,
                description: $changes->description,
                describes: $request->getPayload()->has('description'),
            ),
        )));
    }
}
