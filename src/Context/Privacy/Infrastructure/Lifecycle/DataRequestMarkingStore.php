<?php

declare(strict_types=1);

namespace App\Context\Privacy\Infrastructure\Lifecycle;

use App\Context\Privacy\Domain\Entity\DataRequest;
use App\Context\Privacy\Domain\Enum\RequestStatus;
use App\Library\Types\Model\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Override;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;

final readonly class DataRequestMarkingStore implements MarkingStoreInterface
{
    #[Override]
    public function getMarking(object $subject): Marking
    {
        return new Marking([self::request($subject)->status()->value => 1]);
    }

    /**
     * @param array<array-key, mixed> $context
     */
    #[Override]
    public function setMarking(object $subject, Marking $marking, array $context = []): void
    {
        $place = Type::of(new ArrayCollection($marking->getPlaces())->key())->toString();
        self::request($subject)->changeStatus(RequestStatus::from($place));
    }

    private static function request(object $subject): DataRequest
    {
        return Type::of($subject)->toInstanceOf(DataRequest::class);
    }
}
