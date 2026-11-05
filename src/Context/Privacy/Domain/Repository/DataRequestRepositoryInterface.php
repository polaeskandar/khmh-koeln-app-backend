<?php

declare(strict_types=1);

namespace App\Context\Privacy\Domain\Repository;

use App\Context\Privacy\Domain\Entity\DataRequest;
use Symfony\Component\Uid\Uuid;

interface DataRequestRepositoryInterface
{
    public function save(DataRequest $request): void;

    public function find(Uuid $id): ?DataRequest;

    /**
     * @return list<DataRequest>
     */
    public function all(): array;
}
