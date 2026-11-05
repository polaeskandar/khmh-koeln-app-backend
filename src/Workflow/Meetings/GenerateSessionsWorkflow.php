<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Meetings\Application\Service\SessionGenerator;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Library\Time\Model\LocalDate;
use App\Workflow\Meetings\Model\GeneratedSessions;

final readonly class GenerateSessionsWorkflow
{
    public function __construct(
        private StructureLookup $structure,
        private SessionGenerator $generator,
    ) {
    }

    public function __invoke(LocalDate $today): GeneratedSessions
    {
        $archived = ArchivedStructure::of($this->structure->archivedIds());

        return GeneratedSessions::of($this->generator->generate($today, $archived));
    }
}
