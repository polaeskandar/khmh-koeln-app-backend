<?php

declare(strict_types=1);

namespace App\Context\Learning\Infrastructure\Lifecycle;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Enum\HomeworkStatus;
use App\Library\Types\Model\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Override;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;

final readonly class HomeworkMarkingStore implements MarkingStoreInterface
{
    #[Override]
    public function getMarking(object $subject): Marking
    {
        return new Marking([self::task($subject)->state()->status->value => 1]);
    }

    /**
     * @param array<array-key, mixed> $context
     */
    #[Override]
    public function setMarking(object $subject, Marking $marking, array $context = []): void
    {
        $task = self::task($subject);
        $place = Type::of(new ArrayCollection($marking->getPlaces())->key())->toString();
        $task->changeState($task->state()->withStatus(HomeworkStatus::from($place)));
    }

    private static function task(object $subject): HomeworkAssignment
    {
        return Type::of($subject)->toInstanceOf(HomeworkAssignment::class);
    }
}
