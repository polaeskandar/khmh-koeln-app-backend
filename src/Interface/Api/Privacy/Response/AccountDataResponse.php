<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy\Response;

use App\Workflow\Privacy\Model\AccountData;
use App\Workflow\Privacy\Model\AccountRecord;
use App\Workflow\Privacy\Model\LinkRow;
use App\Workflow\Privacy\Model\MemberLinkView;
use App\Workflow\Privacy\Model\PrivacyAcceptance;
use App\Workflow\Privacy\Model\RegistrationView;
use App\Workflow\Privacy\Model\ReportByRow;
use DateTimeImmutable;
use JsonSerializable;
use Override;

final readonly class AccountDataResponse implements JsonSerializable
{
    public function __construct(
        private AccountData $data,
        private ?MemberDataResponse $memberRecord,
    ) {
    }

    public static function of(?AccountData $data, ?MemberDataResponse $memberRecord): ?self
    {
        return $data === null ? null : new self($data, $memberRecord);
    }

    /**
     * @return array{
     *     kind: string,
     *     exportedAt: DateTimeImmutable,
     *     account: array{
     *         id: string,
     *         email: string,
     *         name: string,
     *         language: string,
     *         createdAt: DateTimeImmutable,
     *         approvedAt: DateTimeImmutable|null,
     *         disabledAt: DateTimeImmutable|null,
     *         privacyNoticeAccepted: PrivacyAcceptance,
     *         roles: list<string>,
     *         registration: RegistrationView|null,
     *         memberAgeCheck: array{result: string, parentAgreed: string|null}|null,
     *     },
     *     children: list<LinkRow>,
     *     homeworkReports: list<ReportByRow>,
     *     memberRecord: MemberDataResponse|null,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'kind' => 'account',
            'exportedAt' => $this->data->exportedAt,
            'account' => [
                ...self::accountOf($this->data->account),
                'memberAgeCheck' => self::ageCheckOf($this->data->member),
            ],
            'children' => $this->data->children,
            'homeworkReports' => $this->data->homeworkReports,
            'memberRecord' => $this->memberRecord,
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     email: string,
     *     name: string,
     *     language: string,
     *     createdAt: DateTimeImmutable,
     *     approvedAt: DateTimeImmutable|null,
     *     disabledAt: DateTimeImmutable|null,
     *     privacyNoticeAccepted: PrivacyAcceptance,
     *     roles: list<string>,
     *     registration: RegistrationView|null,
     * }
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
