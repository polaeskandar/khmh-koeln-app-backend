<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy\Request;

use App\Workflow\Privacy\Model\ReceiptDetails;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ReceiptRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'privacy.requestedByRequired', normalizer: 'trim')]
        #[Assert\Length(max: 200, maxMessage: 'validation.tooLong', normalizer: 'trim')]
        public ?string $requestedBy = null,
        #[Assert\NotNull(message: 'validation.invalidChoice')]
        #[Assert\Choice(choices: ['email', 'letter', 'in_person'], message: 'validation.invalidChoice')]
        public ?string $channel = null,
        #[Assert\NotBlank(message: 'validation.invalidDate')]
        #[Assert\Date(message: 'validation.invalidDate')]
        public ?string $receivedOn = null,
    ) {
    }

    public function details(): ReceiptDetails
    {
        return new ReceiptDetails($this->requestedBy ?? '', $this->channel ?? '', $this->receivedOn ?? '');
    }
}
