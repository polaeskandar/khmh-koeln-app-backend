<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Request;

use App\Context\Identity\Domain\Enum\AgeCheck;
use App\Context\Identity\Domain\Enum\RegisteredAs;
use App\Library\Strings\Model\InputText;
use App\Workflow\Identity\Model\ApprovalChoice;
use App\Workflow\Identity\Model\MemberApproval;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class ApprovalRequest
{
    /**
     * @param list<string> $childIds
     * @param list<string> $roleIds
     * @param list<ApprovalAssignmentRequest> $assignments
     */
    public function __construct(
        #[Assert\NotBlank(message: 'validation.invalidChoice')]
        #[Assert\Choice(callback: [RegisteredAs::class, 'values'], message: 'validation.invalidChoice')]
        public ?string $as = null,
        #[Assert\All([new Assert\Type(type: 'string', message: 'accounts.childNotFound')])]
        public array $childIds = [],
        #[Assert\All([new Assert\Type(type: 'string', message: 'roles.unknown')])]
        public array $roleIds = [],
        #[Assert\Valid]
        public MemberApprovalRequest $member = new MemberApprovalRequest(),
        #[Assert\Valid]
        public array $assignments = [],
    ) {
    }

    public function isMember(): bool
    {
        return $this->as === RegisteredAs::Member->value;
    }

    public function choice(): ApprovalChoice
    {
        return new ApprovalChoice(
            $this->as ?? '',
            $this->roleIds,
            $this->childIds,
            new ArrayCollection($this->assignments)->map(static fn (ApprovalAssignmentRequest $assignment): array => [
                'serviceId' => $assignment->serviceId,
                'stageId' => $assignment->stageId,
            ])->getValues(),
        );
    }

    public function memberApproval(): MemberApproval
    {
        return new MemberApproval(
            $this->member->personId ?? '',
            $this->member->ageCheck ?? '',
            $this->member->guardianConsentNote,
        );
    }

    #[Assert\Callback]
    public function requireTheRecordAndTheAge(ExecutionContextInterface $context): void
    {
        if (!$this->isMember()) {
            return;
        }

        if (InputText::of($this->member->personId)->trimmed()->isEmpty()) {
            $context->buildViolation('accounts.choosePerson')->atPath('member.personId')->addViolation();
        }

        if (AgeCheck::tryFrom($this->member->ageCheck ?? '') === null) {
            $context->buildViolation('accounts.chooseAge')->atPath('member.ageCheck')->addViolation();
        }
    }
}
