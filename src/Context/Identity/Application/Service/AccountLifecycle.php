<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Interface\AdministrationLockInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\ReadableCollection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class AccountLifecycle
{
    public function __construct(
        private UserRepositoryInterface $users,
        private LastAdministrator $lastAdministrator,
        private AdministrationLockInterface $administration,
        private Credentials $credentials,
        private Calendar $calendar,
        private LoggerInterface $logger,
    ) {
    }

    public function ensurePending(Uuid $id): void
    {
        self::ensureUndecided($this->get($id));
    }

    /**
     * @param ReadableCollection<int, Role> $roles
     * @param callable(User): void $alongside
     */
    public function approve(Uuid $id, Uuid $actorId, ReadableCollection $roles, callable $alongside): User
    {
        $actor = $this->get($actorId);

        $this->whileUndecided($id, function (User $user) use ($actor, $roles, $alongside): void {
            $user->changeApproval(
                $actor,
                $user->status()->approvedOn($this->calendar->now()),
                $user->registration()->decided(),
            );

            foreach ($roles as $role) {
                $user->assign($role);
            }

            $alongside($user);
        });

        return $this->get($id);
    }

    /**
     * @param callable(User): void $alongside
     */
    public function decline(Uuid $id, callable $alongside): void
    {
        $this->whileUndecided($id, function (User $user) use ($alongside): void {
            $alongside($user);
            $this->users->remove($user);
            $this->logger->info('Declined an account request.', ['account' => $user->id()->toRfc4122()]);
        });
    }

    public function disable(string $email): void
    {
        $this->administration->whileLocked(function () use ($email): void {
            $user = $this->getByEmail($email);

            if ($this->lastAdministrator->isKeptOnlyByAccount($user)) {
                throw new RuleViolation('roles.lastAdministrator');
            }

            $user->changeStatus($user->status()->disabledAt($this->calendar->now()));
            $this->users->save($user);
            $this->credentials->endAllOf($user->id());
        });
    }

    public function enable(string $email): void
    {
        $user = $this->getByEmail($email);

        $user->changeStatus($user->status()->enabled());
        $this->users->save($user);
    }

    /**
     * @param callable(User): void $decision
     */
    private function whileUndecided(Uuid $id, callable $decision): void
    {
        $this->users->withWriteLock($id, static function (User $user) use ($decision): void {
            self::ensureUndecided($user);
            $decision($user);
        });
    }

    private function get(Uuid $id): User
    {
        return $this->users->find($id) ?? throw NotFound::of('user');
    }

    private function getByEmail(string $email): User
    {
        return $this->users->findByEmail($email) ?? throw NotFound::of('account');
    }

    private static function ensureUndecided(User $user): void
    {
        if (!$user->status()->isPending()) {
            throw new RuleViolation('accounts.alreadyApproved');
        }
    }
}
