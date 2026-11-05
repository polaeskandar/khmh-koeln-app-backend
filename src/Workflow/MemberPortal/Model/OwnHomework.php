<?php

declare(strict_types=1);

namespace App\Workflow\MemberPortal\Model;

use App\Context\Learning\Application\Service\PupilHomework;
use App\Context\Learning\Domain\ValueObject\VisibleTask;
use App\Context\People\Application\Service\MemberRecords;
use App\Workflow\ParentPortal\Model\ChildHomework;
use App\Workflow\ParentPortal\Model\ChildHomeworks;
use Symfony\Component\Uid\Uuid;

final readonly class OwnHomework
{
    private function __construct(
        public ?ChildHomework $homework,
    ) {
    }

    public static function lookedUpIn(MemberRecords $records, PupilHomework $homework, Uuid $personId): self
    {
        return new self(ChildHomeworks::lookedUpIn($records->ownRecord($personId), $homework)[0] ?? null);
    }

    public function task(Uuid $homeworkId): ?VisibleTask
    {
        return $this->homework?->tasks->task($homeworkId);
    }
}
