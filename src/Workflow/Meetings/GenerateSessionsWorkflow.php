<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Meetings\Application\Service\GenerationRuns;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Workflow\Meetings\Model\GeneratedSessions;

final readonly class GenerateSessionsWorkflow
{
    public function __construct(
        private StructureLookup $structure,
        private GenerationRuns $runs,
    ) {
    }

    public function __invoke(?string $date = null): GeneratedSessions
    {
        $day = $this->runs->dayOf($date);

        return GeneratedSessions::of($this->runs->run($day, ArchivedStructure::of($this->structure->archivedIds())));
    }
}
