<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Library\Problem\Exception\InvalidInput;
use App\Library\Time\Exception\InvalidLocalDate;
use App\Library\Time\Model\LocalDate;
use Symfony\Component\Uid\Uuid;

final readonly class MeetingChoice
{
    public const string FIELD = 'meeting';

    public function __construct(
        public ?string $scheduleId = null,
        public ?string $sessionId = null,
        public ?string $date = null,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public static function refusal(string $code, array $params = []): InvalidInput
    {
        return InvalidInput::field(self::FIELD, $code, $params);
    }

    public function session(): Uuid
    {
        return self::idOf($this->sessionId ?? '');
    }

    public function schedule(): Uuid
    {
        return self::idOf($this->scheduleId ?? '');
    }

    public function scheduleWithoutSession(): ?Uuid
    {
        return $this->sessionId === null && $this->scheduleId !== null ? $this->schedule() : null;
    }

    public function day(): LocalDate
    {
        try {
            return LocalDate::of($this->date ?? '');
        } catch (InvalidLocalDate) {
            throw self::refusal('lessons.chooseDate');
        }
    }

    private static function idOf(string $value): Uuid
    {
        return Uuid::isValid($value) ? Uuid::fromString($value) : throw self::refusal('lessons.chooseMeeting');
    }
}
