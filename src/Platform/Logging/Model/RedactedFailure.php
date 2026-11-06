<?php

declare(strict_types=1);

namespace App\Platform\Logging\Model;

use App\Library\Collections\Model\Collections;
use App\Library\Strings\Model\Text;
use Doctrine\Common\Collections\ArrayCollection;
use Exception;
use Override;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class RedactedFailure extends RuntimeException implements HttpExceptionInterface
{
    private const int SERVER_ERROR = 500;

    private const array ARGUMENTS = ['args'];

    private function __construct(
        Throwable $original,
        private readonly int $statusCode,
        ?Throwable $previous,
    ) {
        $message = Text::format('%s: %s', $original::class, SensitiveText::clean($original->getMessage()));
        parent::__construct($message->toString(), (int) $original->getCode(), $previous);

        $this->file = $original->getFile();
        $this->line = $original->getLine();

        new ReflectionProperty(Exception::class, 'trace')->setValue(
            $this,
            new ArrayCollection($original->getTrace())->map(
                static fn (array $frame): array => Collections::withoutKeys(
                    new ArrayCollection($frame),
                    self::ARGUMENTS,
                )->toArray(),
            )->getValues(),
        );
    }

    public static function of(Throwable $exception): self
    {
        $previous = $exception->getPrevious();
        $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : self::SERVER_ERROR;

        return new self($exception, $status, $previous === null ? null : self::of($previous));
    }

    #[Override]
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function getHeaders(): array
    {
        return [];
    }
}
