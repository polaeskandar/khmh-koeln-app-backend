<?php

declare(strict_types=1);

namespace App\Platform\Problem\Service;

use App\Platform\Errors\Contract\ErrorTrackerInterface;
use App\Platform\Http\Service\ApiRequest;
use App\Platform\Problem\Contract\ProblemResponderInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

#[AsEventListener(priority: -64)]
final readonly class ProblemListener
{
    public function __construct(
        private ProblemResponderInterface $responder,
        private ErrorTrackerInterface $errors,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!ApiRequest::is($event->getRequest())) {
            return;
        }

        $response = $this->responder->respond($event->getThrowable());

        if ($response->isServerError()) {
            $this->errors->capture($event->getThrowable());
        }

        $event->setResponse($response);
    }
}
