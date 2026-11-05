<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy\Response;

use App\Workflow\Privacy\Model\AccountData;
use App\Workflow\Privacy\Model\AccountRecord;
use App\Workflow\Privacy\Model\LinkRow;
use App\Workflow\Privacy\Model\MemberData;
use App\Workflow\Privacy\Model\MemberLinkView;
use App\Workflow\Privacy\Model\PrivacyAcceptance;
use App\Workflow\Privacy\Model\RegistrationView;
use App\Workflow\Privacy\Model\ReportByRow;
use JsonSerializable;
use Override;

final readonly class AccountDataResponse implements JsonSerializable
{
    public function __construct(
        private AccountData $data,
        private ?MemberDataResponse $memberRecord,
    ) {
    }

    public static function of(?AccountData $data, ?MemberData $memberRecord): ?self
    {
        return $data === null ? null : new self($data, MemberDataResponse::of($memberRecord));
    }

    /**
     * @return array{
     *     kind: string,
     *     exportedAt: string,
     *     account: array<string, string|list<string>|object|array<string, string|null>|null>,
     *     children: list<LinkRow>,
     *     homeworkReports: list<ReportByRow>,
     *     memberRecord?: MemberDataResponse,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $copy = [
            'kind' => 'account',
            'exportedAt' => $this->data->exportedAt,
            'account' => [
                ...self::accountOf($this->data->account),
                'memberAgeCheck' => self::ageCheckOf($this->data->member),
            ],
            'children' => $this->data->children,
            'homeworkReports' => $this->data->homeworkReports,
        ];

        return $this->memberRecord === null ? $copy : [...$copy, 'memberRecord' => $this->memberRecord];
    }

    /**
     * @return array<string, string|list<string>|PrivacyAcceptance|RegistrationView|null>
     */
    private static function accountOf(AccountRecord $account): array
    {
        return [
            'id' => $account->identity->id,
            'email' => $account->identity->email,
            'name' => $account->identity->name,
            'language' => $account->identity->language,
            'createdAt' => $account->moments->createdAt,
            'approvedAt' => $account->moments->approvedAt,
            'disabledAt' => $account->moments->disabledAt,
            'privacyNoticeAccepted' => $account->privacy,
            'roles' => $account->roles,
            'registration' => $account->registration,
        ];
    }

    /**
     * @return array{result: string, parentAgreed: string|null}|null
     */
    private static function ageCheckOf(?MemberLinkView $member): ?array
    {
        return $member === null ? null : ['result' => $member->result, 'parentAgreed' => $member->parentAgreed];
    }
}
