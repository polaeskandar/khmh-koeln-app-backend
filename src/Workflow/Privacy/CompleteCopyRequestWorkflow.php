<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Privacy\Application\Service\RequestCopies;
use App\Library\Problem\Model\Ids;
use Symfony\Component\Uid\Uuid;

final readonly class CompleteCopyRequestWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private RequestCopies $copies,
    ) {
    }

    public function __invoke(string $requestId, string $viewerId): void
    {
        $this->access->require(Action::Update, Resource::Users, refusal: 'privacy.adminOnly');

        $this->copies->complete(Ids::toUuid($requestId, notFound: 'request'), Uuid::fromString($viewerId));
    }
}
