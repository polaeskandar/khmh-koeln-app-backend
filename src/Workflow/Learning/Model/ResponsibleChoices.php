<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\ValueObject\ResponsibleChoice;
use App\Library\Problem\Model\Ids;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class ResponsibleChoices
{
    /**
     * @param ReadableCollection<int, string> $preparerIds
     */
    public static function of(?string $sent, ReadableCollection $preparerIds): ResponsibleChoice
    {
        $responsible = Ids::toUuidOrNull($sent);

        return new ResponsibleChoice(
            $responsible,
            $responsible !== null && $preparerIds->contains($responsible->toRfc4122()),
        );
    }
}
