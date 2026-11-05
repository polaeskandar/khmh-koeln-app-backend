<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Domain\Entity\Stage;
use App\Workflow\Identity\Model\AccountReference;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class LessonAbilities
{
    /**
     * @param list<AccountReference> $servants
     */
    public function __construct(
        public array $servants,
        public bool $create,
    ) {
    }

    public static function grantedBy(AccessCheck $access, AccountLookup $accounts, Stage $stage): self
    {
        $open = !$stage->isArchived() && !$stage->service()->isArchived();
        $preparers = $accounts->activeAllowedTo(Action::Update, Resource::Lessons);

        return new self(
            servants: new ArrayCollection($preparers)->map(AccountReference::of(...))->getValues(),
            create: $open && $access->allows(Action::Create, Resource::Lessons),
        );
    }
}
