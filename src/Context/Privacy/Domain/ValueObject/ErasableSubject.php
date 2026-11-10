<?php

declare(strict_types=1);

namespace App\Context\Privacy\Domain\ValueObject;

use App\Context\Privacy\Domain\Entity\DataRequest;
use App\Context\Privacy\Domain\Enum\RequestKind;
use App\Context\Privacy\Domain\Enum\RequestStatus;
use App\Library\Problem\Exception\RuleViolation;
use Symfony\Component\Uid\Uuid;

final readonly class ErasableSubject
{
    private function __construct(
        public ?Uuid $personId,
        public ?Uuid $accountId,
        private ?Uuid $viewerId,
    ) {
    }

    public static function of(DataRequest $request, ?Uuid $viewerId): self
    {
        if ($request->status() !== RequestStatus::Open) {
            throw new RuleViolation('privacy.notOpen');
        }

        if ($request->kind() !== RequestKind::Erasure) {
            throw new RuleViolation('privacy.notErasure');
        }

        $subject = $request->subject();

        if ($subject->isGone()) {
            throw new RuleViolation('privacy.subjectGone');
        }

        return new self($subject->personId, $subject->accountId, $viewerId);
    }

    public function joined(?Uuid $record, ?Uuid $account): self
    {
        $joined = new self($this->personId ?? $record, $this->accountId ?? $account, $this->viewerId);

        return $this->viewerId !== null && $joined->accountId?->equals($this->viewerId) === true
            ? throw new RuleViolation('privacy.ownAccount')
            : $joined;
    }
}
