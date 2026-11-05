<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Security;

use App\Context\Identity\Domain\Entity\ApiToken;
use App\Context\Identity\Domain\Repository\ApiTokenRepositoryInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Library\Checksum\Model\Checksum;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\InputText;
use App\Library\Time\Client\Calendar;
use App\Library\Types\Model\Type;
use DateInterval;
use LogicException;
use Override;
use SensitiveParameter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\String\ByteString;
use Symfony\Component\Uid\Uuid;

final readonly class ApiTokenIssuer implements AuthenticationSuccessHandlerInterface
{
    private const string TOKEN_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';

    private const int TOKEN_LENGTH = 43;

    private const int DEVICE_NAME_MAX = 100;

    public function __construct(
        private ApiTokenRepositoryInterface $tokens,
        private UserRepositoryInterface $users,
        private Calendar $calendar,
        private SerializerInterface $serializer,
        #[Autowire(env: 'API_TOKEN_LIFETIME')]
        private string $lifetime,
    ) {
    }

    #[Override]
    public function onAuthenticationSuccess(Request $request, #[SensitiveParameter] TokenInterface $token): Response
    {
        $user = $this->users->find(Uuid::fromString($token->getUserIdentifier())) ?? throw new LogicException(
            'A signed-in account exists.',
        );

        $secret = ByteString::fromRandom(self::TOKEN_LENGTH, self::TOKEN_ALPHABET)->toString();
        $expiresAt = $this->calendar->now()->add(new DateInterval($this->lifetime))->toDateTimeImmutable();
        $this->tokens->save(new ApiToken($user, Checksum::sha256($secret), self::deviceNameOf($request), $expiresAt));

        $body = $this->serializer->serialize(['token' => $secret, 'expiresAt' => $expiresAt], 'json');

        return new JsonResponse($body, status: Response::HTTP_CREATED, json: true);
    }

    private static function deviceNameOf(Request $request): ?string
    {
        $trimmed = InputText::of(Type::of($request->toArray()['deviceName'] ?? null)->toStringOrNull())->trimmed();

        if ($trimmed->length() > self::DEVICE_NAME_MAX) {
            throw InvalidInput::tooLong('deviceName', self::DEVICE_NAME_MAX);
        }

        return $trimmed->toStringOrNull();
    }
}
