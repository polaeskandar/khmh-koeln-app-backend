<?php

declare(strict_types=1);

namespace App\Platform\Problem\Service;

use App\Platform\Http\Service\ApiRequest;
use App\Platform\Problem\Model\Problem;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

#[AsEventListener(priority: -64)]
final readonly class ProblemListener
{
    private const int JSON_OPTIONS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

    public function __construct(
        private ProblemTranslation $translation,
        #[Autowire(param: 'kernel.debug')]
        private bool $debug,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!ApiRequest::is($event->getRequest())) {
            return;
        }

        $exception = $event->getThrowable();
        $problem = $this->translation->of($exception) ?? $this->internal($exception);
        $response = new JsonResponse($problem->toArray(), status: $problem->status, headers: [
            'Content-Type' => 'application/problem+json',
        ]);

        $response->setEncodingOptions(self::JSON_OPTIONS);

        if ($exception instanceof HttpExceptionInterface) {
            $response->headers->add($exception->getHeaders());
        }

        $event->setResponse($response);
    }

    private function internal(Throwable $exception): Problem
    {
        $extensions = new ArrayCollection();
        if ($this->debug) {
            $extensions->set('debug', (object) ['class' => $exception::class, 'message' => $exception->getMessage()]);
        }

        return new Problem(Response::HTTP_INTERNAL_SERVER_ERROR, 'internal', extensions: $extensions);
    }
}
