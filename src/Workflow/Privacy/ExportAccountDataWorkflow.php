<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Learning\Application\Service\HomeworkReports;
use App\Context\People\Domain\Query\GuardianRelationshipQueryInterface;
use App\Context\People\Domain\Query\PersonQueryInterface;
use App\Library\Time\Client\Calendar;
use App\Workflow\Privacy\Model\AccountData;
use App\Workflow\Privacy\Model\AccountRecord;
use App\Workflow\Privacy\Model\LinkRow;
use App\Workflow\Privacy\Model\MemberLinkView;
use App\Workflow\Privacy\Model\ReportByRow;
use Symfony\Component\Uid\Uuid;

final readonly class ExportAccountDataWorkflow
{
    public function __construct(
        private AccountLookup $accounts,
        private PersonQueryInterface $people,
        private GuardianRelationshipQueryInterface $guardians,
        private HomeworkReports $homework,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(string $accountId): ?AccountData
    {
        $user = $this->accounts->findAccount(Uuid::fromString($accountId));

        if ($user === null) {
            return null;
        }

        $reports = $this->homework->listMadeBy($user->id());

        return new AccountData(
            exportedAt: $this->calendar->now(),
            account: AccountRecord::of($user),
            children: LinkRow::childrenOf($this->guardians->listOfAccount($user->id())),
            homeworkReports: ReportByRow::listOf($reports, $this->people->findByIds(ReportByRow::childIdsOf($reports))),
            member: MemberLinkView::of($user),
        );
    }
}
