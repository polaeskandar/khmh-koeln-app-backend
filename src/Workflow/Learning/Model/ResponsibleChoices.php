<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\ValueObject\ResponsibleChoice;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ResponsibleChoices
{
    /**
     * @param ReadableCollection<int, string> $preparerIds
     */
    public static function of(?string $sent, ReadableCollection $preparerIds): ResponsibleChoice
    {
        $responsible = $sent !== null && Uuid::isValid($sent) ? Uuid::fromString($sent) : null;

        return new ResponsibleChoice(
            $responsible,
            $responsible !== null && $preparerIds->contains($responsible->toRfc4122()),
        );
    }
}
