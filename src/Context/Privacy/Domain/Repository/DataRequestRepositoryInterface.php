<?php

declare(strict_types=1);

namespace App\Context\Privacy\Domain\Repository;

use App\Context\Privacy\Domain\Entity\DataRequest;
use Symfony\Component\Uid\Uuid;

interface DataRequestRepositoryInterface
{
    public function save(DataRequest $request): void;

    public function find(Uuid $id): ?DataRequest;

    public function reloaded(Uuid $id): ?DataRequest;

    public function openAppErasureOf(Uuid $accountId): ?DataRequest;

    /**
     * @return list<DataRequest>
     */
    public function all(): array;

    /**
     * @template T of object
     *
     * @param callable(DataRequest): T $work
     *
     * @return T
     */
    public function withWriteLock(Uuid $id, callable $work): object;
}
