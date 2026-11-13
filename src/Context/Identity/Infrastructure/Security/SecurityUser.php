<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Security;

use App\Context\Identity\Domain\Entity\User;
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

    private const string ERASURE_MARK = '|Y-m-d';

    private function __construct(
        private string $id,
        private string $passwordHash,
        private string $passwordFingerprint,
        private string $statusFingerprint,
        private ?User $account,
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
            account: null,
        );
    }

    public static function of(User $user): self
    {
        return new self(
            $user->id()->toRfc4122(),
            $user->passwordHash(),
            Checksum::fingerprint($user->passwordHash()),
            self::fingerprintOf($user),
            $user,
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

    public function account(): ?User
    {
        return $this->account;
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

    private static function fingerprintOf(User $user): string
    {
        $status = $user->status();

        return Text::format(
            '%d|%d%s',
            $status->disabledAt?->getTimestamp() ?? 0,
            $status->authVersion,
            $user->erasesOn()?->format(self::ERASURE_MARK) ?? '',
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
