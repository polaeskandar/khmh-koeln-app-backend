<?php

declare(strict_types=1);

namespace App\Context\Privacy\Application\Service;

use App\Context\Privacy\Domain\Entity\DataRequest;
use App\Context\Privacy\Domain\Repository\DataRequestRepositoryInterface;
use App\Context\Privacy\Domain\ValueObject\RequestClosing;
use App\Platform\Lifecycle\Contract\Transitions;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Workflow\WorkflowInterface;

final readonly class DataRequestLifecycle
{
    public function __construct(
        private DataRequestRepositoryInterface $requests,
        private Transitions $transitions,
        #[Target('data_request_lifecycle')]
        private WorkflowInterface $lifecycle,
    ) {
    }

    /**
     * @param array<string, int>|null $outcome
     */
    public function complete(DataRequest $request, DateTimeImmutable $at, ?Uuid $by, ?array $outcome): DataRequest
    {
        $this->transitions->apply($this->lifecycle, $request, 'complete');
        $request->changeClosing(new RequestClosing($at, $by));
        $request->changeOutcome($outcome);
        $this->requests->save($request);

        return $request;
    }

    public function withdraw(DataRequest $request, DateTimeImmutable $at): DataRequest
    {
        $this->transitions->apply($this->lifecycle, $request, 'withdraw');
        $request->changeClosing(new RequestClosing($at));
        $this->requests->save($request);

        return $request;
    }
}
