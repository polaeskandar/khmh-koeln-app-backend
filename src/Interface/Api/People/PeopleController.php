<?php

declare(strict_types=1);

namespace App\Interface\Api\People;

use App\Interface\Api\People\Request\CreatePersonRequest;
use App\Interface\Api\People\Request\PeopleQueryRequest;
use App\Interface\Api\People\Request\RenamePersonRequest;
use App\Interface\Api\People\Response\CreatedPersonResponse;
use App\Interface\Api\People\Response\PeoplePageResponse;
use App\Interface\Api\People\Response\PersonResponse;
use App\Workflow\People\CreatePersonWorkflow;
use App\Workflow\People\DeactivatePersonWorkflow;
use App\Workflow\People\ListPeopleWorkflow;
use App\Workflow\People\ReactivatePersonWorkflow;
use App\Workflow\People\RenamePersonWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/people')]
final class PeopleController extends AbstractController
{
    #[Route('', name: 'api_people_list', methods: ['GET'])]
    public function list(
        ListPeopleWorkflow $listPeople,
        #[MapQueryString(serializationContext: [
            'allow_extra_attributes' => true,
        ], validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        PeopleQueryRequest $query = new PeopleQueryRequest(),
    ): JsonResponse {
        return $this->json(new PeoplePageResponse($listPeople($query->query())));
    }

    #[Route('', name: 'api_people_create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload]
        CreatePersonRequest $person,
        CreatePersonWorkflow $createPerson,
    ): JsonResponse {
        return $this->json(CreatedPersonResponse::of($createPerson($person->draft())), status: Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_people_rename', methods: ['PUT'])]
    public function rename(
        string $id,
        #[MapRequestPayload]
        RenamePersonRequest $person,
        RenamePersonWorkflow $renamePerson,
    ): JsonResponse {
        return $this->json(PersonResponse::of($renamePerson(
            $id,
            $person->firstName,
            $person->lastName,
            $person->displayName,
        )));
    }

    #[Route('/{id}/deactivate', name: 'api_people_deactivate', methods: ['POST'])]
    public function deactivate(string $id, DeactivatePersonWorkflow $deactivatePerson): JsonResponse
    {
        return $this->json(PersonResponse::of($deactivatePerson($id)));
    }

    #[Route('/{id}/reactivate', name: 'api_people_reactivate', methods: ['POST'])]
    public function reactivate(string $id, ReactivatePersonWorkflow $reactivatePerson): JsonResponse
    {
        return $this->json(PersonResponse::of($reactivatePerson($id)));
    }
}
