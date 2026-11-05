<?php

declare(strict_types=1);

namespace App\Interface\Api\ParentPortal;

use App\Interface\Api\ParentPortal\Response\ChildListResponse;
use App\Workflow\ParentPortal\ListChildrenWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class ParentChildrenController extends AbstractController
{
    #[Route('/api/parent/children', name: 'api_parent_children', methods: ['GET'])]
    public function list(#[CurrentUser] UserInterface $user, ListChildrenWorkflow $listChildren): JsonResponse
    {
        return $this->json(ChildListResponse::of($listChildren($user->getUserIdentifier())));
    }
}
