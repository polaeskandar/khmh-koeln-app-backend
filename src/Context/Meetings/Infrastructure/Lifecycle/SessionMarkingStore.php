<?php

declare(strict_types=1);

namespace App\Context\Meetings\Infrastructure\Lifecycle;

use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Enum\SessionStatus;
use App\Library\Types\Model\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Override;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;

final readonly class SessionMarkingStore implements MarkingStoreInterface
{
    #[Override]
    public function getMarking(object $subject): Marking
    {
        return new Marking([self::session($subject)->progress()->status->value => 1]);
    }

    /**
     * @param array<array-key, mixed> $context
     */
    #[Override]
    public function setMarking(object $subject, Marking $marking, array $context = []): void
    {
        $session = self::session($subject);
        $place = Type::of(new ArrayCollection($marking->getPlaces())->key())->toString();
        $session->changeProgress($session->progress()->withStatus(SessionStatus::from($place)));
    }

    private static function session(object $subject): Session
    {
        return Type::of($subject)->toInstanceOf(Session::class);
    }
}
