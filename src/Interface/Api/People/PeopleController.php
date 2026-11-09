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
use App\Workflow\People\Model\DirectoryEntry;
use App\Workflow\People\ReactivatePersonWorkflow;
use App\Workflow\People\RenamePersonWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
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
    #[OA\Get(summary: 'People list.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: PeoplePageResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function list(
        ListPeopleWorkflow $listPeople,
        #[MapQueryString(serializationContext: [
            'allow_extra_attributes' => true,
        ], validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        PeopleQueryRequest $query = new PeopleQueryRequest(),
    ): JsonResponse {
        return $this->json(new PeoplePageResponse($listPeople($query->filter())));
    }

    #[Route('', name: 'api_people_create', methods: ['POST'])]
    #[OA\Post(summary: 'People create.')]
    #[OA\Response(
        response: 201,
        description: 'Created.',
        content: new OA\JsonContent(ref: new Model(type: CreatedPersonResponse::class)),
    )]
    #[OA\Response(
        response: 409,
        description: 'people.duplicate: people with the same name exist; send duplicates: "create" to add anyway.',
        content: new OA\MediaType(
            mediaType: 'application/problem+json',
            schema: new OA\Schema(
                required: ['status', 'code', 'params', 'candidates'],
                properties: [
                    new OA\Property(property: 'status', type: 'integer'),
                    new OA\Property(property: 'code', type: 'string'),
                    new OA\Property(property: 'params', type: 'object'),
                    new OA\Property(
                        property: 'candidates',
                        type: 'array',
                        items: new OA\Items(ref: new Model(type: DirectoryEntry::class)),
                    ),
                ],
                type: 'object',
            ),
        ),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function create(
        #[MapRequestPayload]
        CreatePersonRequest $person,
        CreatePersonWorkflow $createPerson,
    ): JsonResponse {
        return $this->json(CreatedPersonResponse::of($createPerson($person->choice())), status: Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_people_rename', methods: ['PUT'])]
    #[OA\Put(summary: 'People rename.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: PersonResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
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
    #[OA\Post(summary: 'People deactivate.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object'))]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: PersonResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function deactivate(string $id, DeactivatePersonWorkflow $deactivatePerson): JsonResponse
    {
        return $this->json(PersonResponse::of($deactivatePerson($id)));
    }

    #[Route('/{id}/reactivate', name: 'api_people_reactivate', methods: ['POST'])]
    #[OA\Post(summary: 'People reactivate.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object'))]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: PersonResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function reactivate(string $id, ReactivatePersonWorkflow $reactivatePerson): JsonResponse
    {
        return $this->json(PersonResponse::of($reactivatePerson($id)));
    }
}
