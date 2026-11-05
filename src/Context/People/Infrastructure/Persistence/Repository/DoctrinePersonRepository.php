<?php

declare(strict_types=1);

namespace App\Context\People\Infrastructure\Persistence\Repository;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\PersonStatus;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Library\Problem\Exception\NotFound;
use App\Library\Strings\Model\Text;
use App\Library\Types\Model\ListOf;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrinePersonRepository implements PersonRepositoryInterface
{
    private const string LOCK_ROW = 'SELECT id FROM people.person WHERE id = ? FOR UPDATE';

    private const string SHARE_ROW = 'SELECT id FROM people.person WHERE id = ? FOR SHARE';

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function save(Person $person, Membership ...$memberships): void
    {
        $this->entityManager->persist($person);

        foreach ($memberships as $membership) {
            $this->entityManager->persist($membership);
        }

        $this->entityManager->flush();
    }

    #[Override]
    public function find(Uuid $id): ?Person
    {
        return $this->entityManager->find(Person::class, $id);
    }

    #[Override]
    public function withWriteLock(Uuid $id, callable $work): mixed
    {
        return $this->entityManager->wrapInTransaction(function () use ($id, $work): mixed {
            $this->entityManager->getConnection()->executeQuery(self::LOCK_ROW, [$id->toRfc4122()]);
            $loaded = $this->entityManager->getUnitOfWork()->tryGetById(['id' => $id], Person::class);

            if ($loaded !== false) {
                $this->entityManager->detach($loaded);
            }

            return $work($this->entityManager->find(Person::class, $id) ?? throw NotFound::of('person'));
        });
    }

    #[Override]
    public function withShareLock(Uuid $id, callable $work): mixed
    {
        return $this->entityManager->wrapInTransaction(function () use ($id, $work): mixed {
            $this->entityManager->getConnection()->executeQuery(self::SHARE_ROW, [$id->toRfc4122()]);

            return $work();
        });
    }

    #[Override]
    public function withNameKey(string $key): ReadableCollection
    {
        return new ArrayCollection($this->entityManager->getRepository(Person::class)->findBy(['name.key' => $key], [
            'name.lastName' => 'ASC',
            'name.firstName' => 'ASC',
        ]));
    }

    /**
     * @param list<Uuid> $ids
     */
    #[Override]
    public function findByIds(array $ids): ReadableCollection
    {
        return new ArrayCollection($this->entityManager->getRepository(Person::class)->findBy(['id' => $ids]));
    }

    /**
     * @param list<string> $words
     */
    #[Override]
    public function searchActive(array $words, int $limit): ReadableCollection
    {
        $query = $this->entityManager
            ->createQueryBuilder()
            ->select('p')
            ->addSelect(
                'LOWER(p.name.lastName) AS HIDDEN lastNameKey',
                'LOWER(p.name.firstName) AS HIDDEN firstNameKey',
            )
            ->from(Person::class, 'p')
            ->where('p.status = :active')
            ->setParameter('active', PersonStatus::Active)
            ->orderBy('lastNameKey')
            ->addOrderBy('firstNameKey')
            ->addOrderBy('p.id')
            ->setMaxResults($limit);

        foreach ($words as $index => $word) {
            $query->andWhere(Text::format("p.name.searchText LIKE :word%d ESCAPE '!'", $index)->toString());
            $query->setParameter('word' . $index, '%' . self::escaped($word) . '%');
        }

        return new ArrayCollection(ListOf::instances($query->getQuery()->getResult(), Person::class));
    }

    #[Override]
    public function membershipsOf(ReadableCollection $people): ReadableCollection
    {
        if ($people->isEmpty()) {
            return new ArrayCollection();
        }

        return new ArrayCollection($this->entityManager->getRepository(Membership::class)->findBy([
            'person' => $people->toArray(),
        ], ['id' => 'ASC']));
    }

    private static function escaped(string $word): string
    {
        return Text::of($word)->replaceMatches('/([!%_])/', replacement: '!$1')->toString();
    }
}
