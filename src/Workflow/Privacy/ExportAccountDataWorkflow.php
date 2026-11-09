<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Learning\Application\Service\HomeworkReports;
use App\Context\People\Application\Service\PeopleExports;
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
        private PeopleExports $people,
        private HomeworkReports $homework,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(string $accountId): ?AccountData
    {
        $account = $this->accounts->find(Uuid::fromString($accountId));

        if ($account === null) {
            return null;
        }

        $reports = $this->homework->madeBy($account->id());

        return new AccountData(
            exportedAt: $this->calendar->now(),
            account: AccountRecord::of($account),
            children: LinkRow::childrenOf($this->people->linksOfAccount($account->id())),
            homeworkReports: ReportByRow::listOf($reports, $this->people->people(ReportByRow::childIdsOf($reports))),
            member: MemberLinkView::of($account->member()),
        );
    }
}
