<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Application\Service\AccountErasures;
use App\Context\People\Application\Service\PersonErasures;
use App\Context\Privacy\Domain\Entity\DataRequest;
use App\Library\Problem\Exception\RuleViolation;
use Symfony\Component\Uid\Uuid;

final readonly class ErasableSubject
{
    public function __construct(
        public ?Uuid $personId,
        public ?Uuid $accountId,
    ) {
    }

    public static function of(
        DataRequest $request,
        PersonErasures $people,
        AccountErasures $accounts,
        string $viewerId,
    ): self {
        $subject = $request->subject();
        $person = $people->existing($subject->personId ?? $accounts->recordOfAccount($subject->accountId));
        $account = $accounts->existing($subject->accountId ?? $accounts->accountOfRecord($subject->personId));

        if ($person === null && $account === null) {
            throw new RuleViolation('privacy.subjectGone');
        }

        if ($account?->toRfc4122() === $viewerId) {
            throw new RuleViolation('privacy.ownAccount');
        }

        return new self($person, $account);
    }
}
