<?php

declare(strict_types=1);

namespace App\Tests\Support\Workflow\Learning\Model;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Learning\Application\Service\HomeworkReports;
use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Repository\HomeworkRepositoryInterface;
use App\Context\Learning\Domain\Repository\LessonRepositoryInterface;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\StageRosters;
use App\Context\People\Domain\Repository\GuardianRelationshipRepositoryInterface;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Context\People\Domain\Repository\RosterRepositoryInterface;
use App\Library\Time\Client\Calendar;
use App\Workflow\Learning\Model\AudienceKeys;
use App\Workflow\Learning\Model\TaskAudiences;
use PHPUnit\Framework\Assert;
use Symfony\Component\DependencyInjection\ContainerInterface;

final readonly class TaskAudienceLookups
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    /**
     * @param list<HomeworkAssignment> $tasks
     */
    public function audiences(array $tasks): TaskAudiences
    {
        $reports = new HomeworkReports(
            $this->service(HomeworkRepositoryInterface::class),
            $this->service(LessonRepositoryInterface::class),
            $this->service(Calendar::class),
        )->of($tasks);

        $keys = AudienceKeys::of(
            $this->service(StructureLookup::class)->stages(AudienceKeys::stageIdsOf($reports)),
            $reports->today,
        );

        $book = $this->rosters()->on($keys->getValues(), $reports->reporterIds());
        $accounts = $this->service(AccountLookup::class)->accounts(TaskAudiences::accountIdsOf($book, $reports));

        return TaskAudiences::of($reports, $keys, $book, $accounts);
    }

    public function rosters(): StageRosters
    {
        return new StageRosters(
            $this->service(RosterRepositoryInterface::class),
            $this->service(PersonRepositoryInterface::class),
            $this->service(GuardianRelationshipRepositoryInterface::class),
        );
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $class): object
    {
        $service = $this->container->get($class);
        Assert::assertInstanceOf($class, $service);

        return $service;
    }
}
