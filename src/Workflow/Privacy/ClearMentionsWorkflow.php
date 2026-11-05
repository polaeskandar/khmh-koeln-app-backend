<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Learning\Application\Service\LearningErasures;
use App\Context\Meetings\Application\Service\MeetingErasures;
use App\Workflow\Privacy\Model\ErasureChoice;
use App\Workflow\Privacy\Model\Mentions;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ClearMentionsWorkflow
{
    public function __construct(
        private ListMentionsWorkflow $listMentions,
        private MeetingErasures $meetings,
        private LearningErasures $learning,
    ) {
    }

    public function __invoke(?Uuid $personId, ErasureChoice $choice): int
    {
        if ($personId === null || !$choice->clearNotes) {
            return 0;
        }

        $mentions = ($this->listMentions)($personId);

        foreach ($mentions as $mention) {
            if ($mention->where === Mentions::LESSON_NOTE) {
                $this->learning->clearLessonNote($mention->id);

                continue;
            }

            $this->meetings->clearNote($mention->where, $mention->id);
        }

        return new ArrayCollection($mentions)->count();
    }
}
