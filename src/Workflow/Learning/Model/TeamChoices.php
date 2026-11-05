<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Domain\ValueObject\HelperChoice;
use App\Context\Learning\Domain\ValueObject\TeamChoice;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class TeamChoices
{
    public static function lookedUpIn(AccountLookup $accounts, LessonChoice $choice): TeamChoice
    {
        return new TeamChoice(
            ResponsibleChoices::lookedUpIn($accounts, $choice->responsibleId),
            self::helpersIn($accounts, $choice->details->collaboratorIds),
        );
    }

    /**
     * @param list<string>|null $sent
     */
    private static function helpersIn(AccountLookup $accounts, ?array $sent): HelperChoice
    {
        if ($sent === null) {
            return new HelperChoice(sent: null, preparers: []);
        }

        $ids = new ArrayCollection($sent)->map(static fn (string $id): string => Uuid::fromString($id)->toRfc4122());

        $preparers = $ids->filter(static fn (string $id): bool => $accounts->isActiveAndAllowedTo(
            Uuid::fromString($id),
            Action::Update,
            Resource::Lessons,
        ));

        return new HelperChoice($ids->getValues(), $preparers->getValues());
    }
}
