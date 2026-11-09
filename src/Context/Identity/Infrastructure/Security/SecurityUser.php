<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Security;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\Identity\Domain\ValueObject\AccountStatus;
use App\Library\Checksum\Model\Checksum;
use App\Library\Strings\Model\Text;
use LogicException;
use Override;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    private const string ID = 'id';

    private const string PASSWORD_FINGERPRINT = 'passwordFingerprint';

    private const string STATUS_FINGERPRINT = 'statusFingerprint';

    private function __construct(
        private string $id,
        private string $passwordHash,
        private string $passwordFingerprint,
        private string $statusFingerprint,
        private ?AccountStatus $status,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function __serialize(): array
    {
        return [
            self::ID => $this->id,
            self::PASSWORD_FINGERPRINT => $this->passwordFingerprint,
            self::STATUS_FINGERPRINT => $this->statusFingerprint,
        ];
    }

    /**
     * @param array<string, string> $data
     */
    public function __unserialize(array $data): void
    {
        $this->__construct(
            id: self::stringIn($data, self::ID),
            passwordHash: '',
            passwordFingerprint: self::stringIn($data, self::PASSWORD_FINGERPRINT),
            statusFingerprint: self::stringIn($data, self::STATUS_FINGERPRINT),
            status: null,
        );
    }

    public static function of(Account $account): self
    {
        return new self(
            $account->id()->toRfc4122(),
            $account->passwordHash(),
            Checksum::fingerprint($account->passwordHash()),
            self::fingerprintOf($account->status()),
            $account->status(),
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

    public function status(): ?AccountStatus
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

        return $sameCredentials && $user->statusFingerprint === $this->statusFingerprint;
    }

    private static function fingerprintOf(AccountStatus $status): string
    {
        return Text::format(
            '%d|%d|%d',
            $status->approvedAt?->getTimestamp() ?? 0,
            $status->disabledAt?->getTimestamp() ?? 0,
            $status->authVersion,
        )->toString()
            |> Checksum::fingerprint(...);
    }

    /**
     * @param array<string, string> $data
     */
    private static function stringIn(array $data, string $key): string
    {
        return $data[$key] ?? '';
    }
}
