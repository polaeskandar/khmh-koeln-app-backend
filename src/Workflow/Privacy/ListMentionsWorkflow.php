<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Learning\Application\Service\LearningErasures;
use App\Context\Meetings\Application\Service\MeetingErasures;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\PersonErasures;
use App\Workflow\Privacy\Model\ErasedShares;
use App\Workflow\Privacy\Model\Mention;
use App\Workflow\Privacy\Model\Mentions;
use Symfony\Component\Uid\Uuid;

final readonly class ListMentionsWorkflow
{
    public function __construct(
        private PersonErasures $people,
        private MeetingErasures $meetings,
        private LearningErasures $learning,
        private StructureLookup $structure,
    ) {
    }

    /**
     * @return list<Mention>
     */
    public function __invoke(Uuid $personId): array
    {
        $person = $this->people->erasable($personId);
        $stageIds = Mentions::stageIdsOf($person);

        return Mentions::of(
            $person,
            $this->meetings->notesAbout(ErasedShares::placesOf($person), $person->id),
            $this->learning->lessonNotesOf($stageIds),
            $this->structure->stages($stageIds),
        );
    }
}
