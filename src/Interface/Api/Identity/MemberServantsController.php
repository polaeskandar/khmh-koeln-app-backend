<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity;

use App\Workflow\Identity\ListMemberServantsWorkflow;
use App\Workflow\Identity\Model\GroupServant;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/member')]
final class MemberServantsController extends AbstractController
{
    #[Route('/servants', name: 'api_member_servants', methods: ['GET'])]
    #[OA\Get(summary: 'Member servants.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(required: ['items'], properties: [
            new OA\Property(
                property: 'items',
                type: 'array',
                items: new OA\Items(ref: new Model(type: GroupServant::class)),
            ),
        ]),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function servants(
        #[CurrentUser]
        UserInterface $viewer,
        ListMemberServantsWorkflow $listServants,
    ): JsonResponse {
        return $this->json(['items' => $listServants($viewer->getUserIdentifier())]);
    }
}
