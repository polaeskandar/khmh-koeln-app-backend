<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy\Request;

use App\Workflow\Privacy\Model\DataRequestChoice;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RecordDataRequestRequest
{
    public function __construct(
        #[Assert\NotNull(message: 'validation.invalidChoice')]
        #[Assert\Choice(choices: ['erasure', 'copy'], message: 'validation.invalidChoice')]
        public ?string $kind = null,
        #[Assert\Valid]
        public RequestSubjectRequest $subject = new RequestSubjectRequest(),
        #[Assert\Valid]
        public ReceiptRequest $receipt = new ReceiptRequest(),
        #[Assert\Length(max: 2000, maxMessage: 'validation.tooLong', normalizer: 'trim')]
        public ?string $note = null,
    ) {
    }

    public function choice(): DataRequestChoice
    {
        return new DataRequestChoice(
            $this->kind ?? '',
            $this->subject->personId,
            $this->subject->accountId,
            $this->receipt->choice(),
            $this->note,
        );
    }
}
