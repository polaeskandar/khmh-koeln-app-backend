<?php

declare(strict_types=1);

namespace App\Platform\Problem\Service;

use App\Platform\Http\Service\ApiRequest;
use App\Platform\Problem\Contract\ProblemResponder;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

#[AsEventListener(priority: -64)]
final readonly class ProblemListener
{
    public function __construct(
        private ProblemResponder $responder,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!ApiRequest::is($event->getRequest())) {
            return;
        }

        $event->setResponse($this->responder->respond($event->getThrowable()));
    }
}
