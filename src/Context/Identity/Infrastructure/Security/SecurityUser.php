<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Security;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\ValueObject\AccountStatus;
use App\Library\Checksum\Model\Checksum;
use LogicException;
use Override;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    private function __construct(
        private string $id,
        private string $passwordHash,
        private string $passwordFingerprint,
        private AccountStatus $status,
    ) {
    }

    /**
     * @return array<string, string|AccountStatus>
     */
    public function __serialize(): array
    {
        return [
            "\0" . self::class . "\0id" => $this->id,
            "\0" . self::class . "\0passwordHash" => '',
            "\0" . self::class . "\0passwordFingerprint" => $this->passwordFingerprint,
            "\0" . self::class . "\0status" => $this->status,
        ];
    }

    public static function of(User $user): self
    {
        return new self(
            $user->id()->toRfc4122(),
            $user->passwordHash(),
            Checksum::fingerprint($user->passwordHash()),
            $user->status(),
        );
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    /**
     * @return non-empty-string
     */
    #[Override]
    public function getUserIdentifier(): string
    {
        return $this->id !== '' ? $this->id : throw new LogicException('An account always has an id.');
    }

    #[Override]
    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    public function status(): AccountStatus
    {
        return $this->status;
    }

    #[Override]
    public function isEqualTo(UserInterface $user): bool
    {
        if (!$user instanceof self) {
            return false;
        }

        $sameCredentials = $user->id === $this->id && $user->passwordFingerprint === $this->passwordFingerprint;

        return $sameCredentials && $user->status->equals($this->status);
    }
}
