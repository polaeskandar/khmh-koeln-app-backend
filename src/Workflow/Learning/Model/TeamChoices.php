<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\Learning\Domain\ValueObject\HelperChoice;
use App\Context\Learning\Domain\ValueObject\TeamChoice;
use App\Library\Collections\Model\Collections;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class TeamChoices
{
    /**
     * @param list<Account> $preparers the active accounts that may prepare lessons
     */
    public static function of(LessonChoice $choice, array $preparers): TeamChoice
    {
        $preparerIds = new ArrayCollection($preparers)->map(
            static fn (Account $account): string => $account->id()->toRfc4122(),
        );

        $helpers = Collections::mapPresent(
            new ArrayCollection($choice->details->collaboratorIds),
            static fn (string $id): ?string => Uuid::isValid($id) ? Uuid::fromString($id)->toRfc4122() : null,
        );

        return new TeamChoice(
            ResponsibleChoices::of($choice->responsibleId, $preparerIds),
            new HelperChoice($helpers->getValues(), $helpers->filter($preparerIds->contains(...))->getValues()),
        );
    }
}
