<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy\Response;

use App\Context\People\Domain\Enum\PersonStatus;
use App\Workflow\Privacy\Model\AttendanceRow;
use App\Workflow\Privacy\Model\LinkRow;
use App\Workflow\Privacy\Model\MemberActivity;
use App\Workflow\Privacy\Model\MemberData;
use App\Workflow\Privacy\Model\MembershipRow;
use App\Workflow\Privacy\Model\ReportAboutRow;
use DateTimeImmutable;
use JsonSerializable;
use Override;

final readonly class MemberDataResponse implements JsonSerializable
{
    public function __construct(
        private MemberData $data,
        private MemberActivity $activity,
    ) {
    }

    public static function of(?MemberData $data, ?MemberActivity $activity): ?self
    {
        return $data === null || $activity === null ? null : new self($data, $activity);
    }

    /**
     * @return array{
     *     kind: 'member',
     *     exportedAt: DateTimeImmutable,
     *     member: array{
     *         id: string,
     *         firstName: string,
     *         lastName: string,
     *         displayName: string|null,
     *         status: PersonStatus,
     *         recordedSince: string,
     *     },
     *     memberships: list<MembershipRow>,
     *     attendance: list<AttendanceRow>,
     *     homeworkReports: list<ReportAboutRow>,
     *     parentAccounts: list<LinkRow>,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $member = $this->data->member;

        return [
            'kind' => 'member',
            'exportedAt' => $this->data->exportedAt,
            'member' => [
                'id' => $member->name->id,
                'firstName' => $member->name->firstName,
                'lastName' => $member->name->lastName,
                'displayName' => $member->name->displayName,
                'status' => $member->status,
                'recordedSince' => $member->recordedSince,
            ],
            'memberships' => $this->data->memberships,
            'attendance' => $this->activity->attendance,
            'homeworkReports' => $this->activity->homeworkReports,
            'parentAccounts' => $this->data->parentAccounts,
        ];
    }
}
