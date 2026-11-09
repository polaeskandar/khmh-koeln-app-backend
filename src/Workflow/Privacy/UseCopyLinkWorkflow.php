<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Privacy\Application\Service\CopyLinks;
use App\Workflow\Privacy\Model\CopySubject;
use SensitiveParameter;

final readonly class UseCopyLinkWorkflow
{
    public function __construct(
        private CopyLinks $links,
    ) {
    }

    public function __invoke(#[SensitiveParameter] string $token): ?CopySubject
    {
        return CopySubject::of($this->links->spend($token));
    }
}
