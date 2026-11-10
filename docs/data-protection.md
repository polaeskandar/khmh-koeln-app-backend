# Data protection

How the app handles personal data, so the office answers every request within one month (Art. 12(3) GDPR) and
the church's data protection adviser can complete the Art. 30 record and the DPIA screening. The data model is in
[data-model.md](data-model.md#data-protection); the hosting choices in [operations.md](operations.md).

## Inventory

One row per table. "Who sees it" follows D7: a role holds church-wide or only in the groups (services and stages) the
account is assigned to; parents see only their own approved children.
"Retention" is `open` until the church decides, with the owner who decides.

| Table | Personal fields | Purpose | Who sees it | Retention | On erasure |
| --- | --- | --- | --- | --- | --- |
| `identity.app_user` | email, display name, language, password hash, birth date, address, registration note, privacy acceptance, email confirmation, approval and disable dates, the date a requested erasure is due | Accounts for staff and parents | Administrators; the account itself | While the account is used; open (office) | `erase-account`: deleted |
| `identity.user_role` | which account has which role | Access control | Administrators | With the account | Deleted with the account |
| `identity.role`, `identity.permission`, `identity.role_permission` | none | Access rules | Administrators | Permanent | – |
| `identity.api_token` | hashed token, its account, device name, created, last used, expiry and revocation | Signing in the iOS and Android apps | Nobody (hashes only) | 90 days (`API_TOKEN_LIFETIME`), or until revoked | Deleted with the account |
| `identity.email_confirmation_token` | hashed one-time token, its account | Confirming a registration's address | Nobody (hashes only) | 7 days, then unusable; unconfirmed registrations are purged after 7 days once their newest link has expired (`app:user:purge-unconfirmed`) | Deleted with the account |
| `identity.password_token` | hashed one-time token, its account, who created it | Password links | Nobody (hashes only) | 24 h, then unusable | Deleted with the account |
| `organization.service`, `organization.stage` | none | Services and classes | Staff | Permanent | – |
| `organization.registration_service` | which services a registering servant chose | Approving registrations | Administrators | Until the request is decided | Deleted with the account |
| `organization.staff_assignment` | which account serves in which service or stage, since when | Limiting servants and service leaders to the groups they serve in | Administrators; service leaders for their services; the account itself (`GET /api/me`); members, for the servants of their own groups (`GET /api/member/servants`) | While the account serves there; removed by an administrator or a service leader | Deleted with the account |
| `people.person` | names, display name, status, birth date, address | Members (children, youth, servants as members) | Staff (`members: read`); parents for their own children | While a member; open (service leaders) | `erase-person`: deleted |
| `people.membership` | which person is in which service or stage, from, until | Rosters | Staff | With the person | Deleted with the person |
| `people.guardian_relationship` | which account is a parent of which person, link status | Parent portal access | Administrators; the parent | While the link is approved | Deleted with the person or the account |
| `meetings.meeting_schedule` | none | Recurring meetings | Staff | Permanent | – |
| `meetings.meeting_session` | who opened and closed it, cancel reason (may name children) | Attendance sessions | Staff; cancel reasons only with `attendance: read` | Permanent (totals) | Actors set to null; erased people kept as anonymous counters |
| `meetings.session_event` | actor, note (may name children) | Session history | Staff with `attendance: read` for notes | Permanent | Actor set to null |
| `meetings.attendance` | who was present, who recorded or removed the mark, removal reason (may name children) | Attendance | Staff (`attendance: read`) | Open (service leaders) | Deleted with the person; actors set to null |
| `learning.lesson` | responsible servant, notes (may name children) | Lesson planning | Staff; notes only with `members: read` | Open (service leaders) | Responsible set to null |
| `learning.lesson_collaborator` | which account helps with which lesson | Lesson planning | Staff | With the lesson | Deleted with the account or the lesson |
| `learning.homework_assignment` | who created it | Homework | Staff; published tasks to the children's parents | Open (service leaders) | Creator set to null |
| `learning.homework_completion` | which child did which task, who reported and undid it | Homework progress | Staff (`members: read`); the child's parents | Open (service leaders) | Deleted with the person; reporters set to null |
| `privacy.data_request` | who it is about, who asked, channel, note, outcome, the date an erasure from the app is due | The office's request log | Administrators | Open (office and adviser) | Subject set to null and described in `erased_subject_*`; closer set to null |
| `privacy.copy_link` | hashed one-time token, its request, the account it was emailed to, expiry and use | Handing out copies | Administrators (never the token) | 7 days, then unusable; kept with the request | Recipient set to null when the account is erased |
| `public.sessions` | sign-in session data of an account | Staying signed in | Nobody (server only) | `SESSION_LIFETIME` (30 days), then garbage-collected | Ends when the account is erased (401) |

## Processors and data locations

| Processor | What | Where | Art. 28 DPA |
| --- | --- | --- | --- |
| Hosting provider (chosen on Tue 3 Nov, see [operations.md § Shortlisted hosts](operations.md#shortlisted-hosts)) | Server, managed PostgreSQL, backups, object storage | EU region | To sign before launch |
| Mailgun (EU region) | Transactional emails: password links, registration notices | EU | To sign before launch |

No other processor receives personal data. Logs leave the server only without personal data (see Logging).

## Technical and organisational measures

- **Transport:** TLS only; HSTS; the session cookie is `__Host-`, `Secure`, `HttpOnly`, `SameSite=Lax`.
- **Backups:** daily, encrypted with age before leaving the database host; the private key is offline with two
  named people; restores only at the provider ([operations.md § Backups](operations.md#backups)).
- **Access:** every workflow checks its permission (`AccessCheck`) before it reads or writes, and for data of a
  service or stage also the group: servants and service leaders reach only the groups they are assigned to
  (`organization.staff_assignment`), anything else answers as if it did not exist; parent routes deny
  by default and answer the same 404 for anything that is not the parent's; servant notes and cancel reasons need
  extra permissions.
- **Passwords:** hashed with Symfony's `auto` hasher; set through one-time links valid 24 h; sign-in is throttled.
- **Logs:** no names, emails or children's data in production logs; request ids instead.
- **Production access:** only the named operators, through the provider's console with two-factor sign-in.
- **Fictional data outside production:** development uses data entered by hand in the app and tests use fixtures,
  never copies of production.
- **Confidentiality:** every servant and operator signs the church's confidentiality undertaking before getting an
  account.

## Procedures

| Request | Who acts | Deadline | Steps |
| --- | --- | --- | --- |
| **Copy** (Art. 15/20) | Office, with an administrator | One month from receipt | 1. Record it (`POST /api/data-requests`). 2. Check the requester's identity (in person, or a reply from the account's email). 3. Create a download link (`POST /api/data-requests/{id}/copy-link`): it works once and for 7 days, and a new link voids the previous one. For an account, or a member record linked to an account, it is emailed to that account's address; otherwise hand the link over safely (in person, or an encrypted email). 4. The download (`POST /api/data-copies`) closes the request; the request shows when the link was used. If the link expires unused, create a new one. On the console instead: `app:privacy:export --person=… \| --account=… --output=…`, hand the file over safely, delete local copies and close the request. An account can also download its own copy in the app (`GET /api/me/personal-data`); a copy for a parent about a child goes through the office, with a link. |
| **Erasure** (Art. 17) | Administrator | One month | **Made in the app** (`POST /api/me/erasure-request`, with the account's password): the request is in the log with the channel `app` and `erasesOn` 14 days later; every session and token of the account ends at once and signing in answers `auth.erasureScheduled` until then. The person can withdraw it with their email and password (`POST /api/auth/erasure-cancellation`); the request closes as `withdrawn`. On `erasesOn` the daily `app:privacy:erase-due` erases the account and, when it is linked to its own member record, that record too (attendance marks, homework reports, guardian links, roles), clears the notes that name the member, because nobody reviews them, and closes the request with the counts; a parent's children keep their records. The office can still erase earlier through the review below. While the request waits, add its lines to the [erasure ledger](#erasure-ledger): the request's details show the account's id, and `GET /api/member-accounts` the member record linked to it; once erased, the log keeps neither. **Recorded by the office:** 1. Record it. 2. Revoke access first (`app:user:disable`). 3. Open the request's erasure review (`GET /api/data-requests/{id}/erasure`): it lists what goes, what stays and the notes that name the member. 4. Erase it there, clearing those notes unless they must stay (`POST /api/data-requests/{id}/erase`); the request closes with the counts. On the console instead: `app:privacy:erase-person <id>` and/or `app:privacy:erase-account <id>` (`:dry-run` first), then close the request. 5. Add the erased ids to the [erasure ledger](#erasure-ledger). |
| **Rectification** (Art. 16) | Service leader (members), administrator (accounts) | One month | Correct the record in the app; tell the requester. |
| **Objection** (Art. 21) | Office with the adviser | One month | Weigh the church's legitimate interest; record the decision. |
| **Breach** (Art. 33) | Administrator, then the adviser | 72 h after becoming aware | Contain, assess, report to the authority; inform families when the risk is high. |
| **Servant leaves** | Service leader | The same week | `app:user:disable <email>`; take the role and remove the assignments (`DELETE /api/assignments/{id}`). |
| **Password link** | Administrator | – | Check the person's identity before sending one (`app:user:password-link`). |
| **Restore** | Operator | Before the app is reopened | Replay every [erasure ledger](#erasure-ledger) entry made after the backup was taken. |

### Erasure ledger

A restored backup can be older than an erasure, and so is the request log inside it. The log in the database
can't replay erasures either: once a subject is erased it keeps only `erased_subject_kind` and
`erased_subject_group`, never the ids. So every erasure is also written to the erasure ledger, which lives
outside the database and its backups. It is an encrypted file in the office's records that only administrators
can open.

- **One line per erasure:** the date, the request id, `person` or `account`, and the erased id. No names, no
  emails.
- **Kept as long as a backup older than the entry exists:** the provider keeps backups for 30 days
  ([operations.md § Backups](operations.md#backups)), so older entries are removed.
- **After a restore:** before reopening the app, the operator replays every entry newer than the backup with
  `app:privacy:erase-person <id>` or `app:privacy:erase-account <id>`, then records the replay in the ledger.

## Open items for the church

| Item | Owner | Status |
| --- | --- | --- |
| Final privacy notice (`app.privacy_notice_version`) | Church council | Open |
| Legal bases per purpose | Data protection adviser | Open |
| DPAs with the host and Mailgun | Church council | Open |
| Art. 30 record of processing | Data protection adviser | Open |
| DPIA screening (children's data, Art. 9) | Data protection adviser | Open |
| Whether a DPO is required | Church council | Open |
| Retention periods (the `open` rows above) | Service leaders, office | Open |
| Impressum | Church council | Open |
| Informing families before launch | Service leaders | Open |
