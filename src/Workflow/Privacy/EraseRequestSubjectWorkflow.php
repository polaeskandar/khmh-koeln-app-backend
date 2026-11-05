<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Privacy\Application\Service\RequestErasures;
use App\Context\Privacy\Domain\ValueObject\ErasableSubject;
use App\Platform\Privacy\Contract\AccountReferences;
use App\Workflow\Privacy\Model\ErasureChoice;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Uid\Uuid;

final readonly class EraseRequestSubjectWorkflow
{
    /**
     * @param iterable<AccountReferences> $references
     */
    public function __construct(
        private AccessCheck $access,
        private RequestErasures $requests,
        private EraseSubjectWorkflow $eraseSubject,
        #[AutowireIterator(AccountReferences::TAG)]
        private iterable $references,
    ) {
    }

    public function __invoke(string $requestId, string $viewerId, ErasureChoice $choice): void
    {
        $this->access->require(Action::Update, Resource::Users, refusal: 'privacy.adminOnly');

        $erase = function (ErasableSubject $subject) use ($requestId, $viewerId, $choice): ?Uuid {
            $outcome = ($this->eraseSubject)($subject, $choice);
            $this->requests->complete($requestId, $viewerId, $outcome->counts());

            return $subject->accountId;
        };

        $accountId = $this->requests->whileErasable($requestId, $viewerId, $erase);

        if ($accountId !== null) {
            $this->sweep($accountId);
        }
    }

    private function sweep(Uuid $accountId): void
    {
        foreach ($this->references as $context) {
            $context->forget($accountId);
        }
    }
}
