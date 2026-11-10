<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Request;

use App\Context\Identity\Domain\Enum\RegisteredAs;
use App\Library\Strings\Model\InputText;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class RegistrationRequest
{
    /**
     * @param list<string> $serviceIds
     */
    public function __construct(
        #[Assert\NotBlank(message: 'accounts.chooseRegisteredAs')]
        #[Assert\Choice(callback: [RegisteredAs::class, 'values'], message: 'accounts.chooseRegisteredAs')]
        public ?string $registeredAs = null,
        #[Assert\Length(max: 1000, maxMessage: 'validation.tooLong')]
        public ?string $note = null,
        #[Assert\Unique(message: 'accounts.chooseService')]
        #[Assert\All([new Assert\Uuid(message: 'accounts.chooseService')])]
        public array $serviceIds = [],
    ) {
    }

    #[Assert\Callback]
    public function requireANote(ExecutionContextInterface $context): void
    {
        if (!InputText::of($this->note)->trimmed()->isEmpty()) {
            return;
        }

        $code = match (RegisteredAs::tryFrom($this->registeredAs ?? '')) {
            RegisteredAs::Servant => 'accounts.serviceDetailsRequired',
            RegisteredAs::Member => 'accounts.groupsRequired',
            default => 'accounts.childrenRequired',
        };

        $context->buildViolation($code)->atPath('note')->addViolation();
    }
}
