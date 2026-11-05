<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\PersonDirectory;
use App\Workflow\People\Model\DirectoryEntries;
use App\Workflow\People\Model\DirectoryEntry;

final readonly class SearchDirectoryWorkflow
{
    private const int LIMIT = 20;

    public function __construct(
        private AccessCheck $access,
        private PersonDirectory $directory,
        private StructureLookup $structure,
    ) {
    }

    /**
     * @return list<DirectoryEntry>
     */
    public function __invoke(string $text): array
    {
        $this->access->require(Action::Read, Resource::Members);

        $people = $this->directory->search($text, self::LIMIT);

        return DirectoryEntries::lookedUpIn($this->directory, $this->structure, $people);
    }
}
