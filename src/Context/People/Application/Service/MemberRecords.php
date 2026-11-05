<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\PersonStatus;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Library\Problem\Exception\InvalidInput;
use Symfony\Component\Uid\Uuid;

final readonly class MemberRecords
{
    public function __construct(
        private PersonRepositoryInterface $people,
    ) {
    }

    /**
     * @param callable(Person): void $work
     */
    public function whileLinkable(string $personId, callable $work): void
    {
        $id = Uuid::isValid($personId) ? Uuid::fromString($personId) : throw self::notFound();

        $this->people->withShareLock($id, function () use ($id, $work): void {
            $person = $this->people->find($id);

            if ($person?->status() !== PersonStatus::Active) {
                throw self::notFound();
            }

            $work($person);
        });
    }

    private static function notFound(): InvalidInput
    {
        return InvalidInput::field('personId', 'accounts.personNotFound');
    }
}
