<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\ServiceArchives;

final readonly class RestoreServiceWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ServiceArchives $archives,
    ) {
    }

    public function __invoke(string $id): void
    {
        $this->access->require(Action::Delete, Resource::Services, refusal: 'services.adminOnlyRestore');

        $this->archives->restore($id);
    }
}
