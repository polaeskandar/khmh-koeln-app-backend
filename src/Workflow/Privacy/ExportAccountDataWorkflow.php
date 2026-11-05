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
        $user = $this->accounts->account(Uuid::fromString($accountId));

        if ($user === null) {
            return null;
        }

        return new AccountData(
            exportedAt: $this->calendar->formatInstant($this->calendar->now()),
            account: AccountRecord::of($user, $this->calendar),
            children: LinkRow::childrenOf($this->people->linksOfAccount($user->id())),
            homeworkReports: ReportByRow::listOf($this->homework->madeBy($user->id()), $this->people, $this->calendar),
            member: MemberLinkView::of($user->member()),
        );
    }
}
