<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Privacy\Application\Service\RequestCopies;
use App\Library\Problem\Model\Ids;
use App\Workflow\Privacy\Model\CopySubject;

final readonly class ShowCopySubjectWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private RequestCopies $copies,
    ) {
    }

    public function __invoke(string $requestId): CopySubject
    {
        $this->access->require(Action::Update, Resource::Users, refusal: 'privacy.adminOnly');

        return CopySubject::of($this->copies->getSubjectOf(Ids::toUuid($requestId, notFound: 'request')));
    }
}
