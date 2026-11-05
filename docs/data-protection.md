# Data protection

How the app handles personal data, so the office answers every request within one month (Art. 12(3) GDPR) and
the church's data protection adviser can complete the Art. 30 record and the DPIA screening. The data model is in
[data-model.md](data-model.md#data-protection); the hosting choices in [operations.md](operations.md).

## Inventory

One row per table. "Who sees it" follows D7: roles are global; parents see only their own approved children.
"Retention" is `open` until the church decides, with the owner who decides.

| Table | Personal fields | Purpose | Who sees it | Retention | On erasure |
| --- | --- | --- | --- | --- | --- |
| `identity.app_user` | email, display name, language, password hash, birth date, address, registration note, privacy acceptance, approval and disable dates | Accounts for staff and parents | Administrators; the account itself | While the account is used; open (office) | `erase-account`: deleted |
| `identity.user_role` | which account has which role | Access control | Administrators | With the account | Deleted with the account |
| `identity.role`, `identity.permission`, `identity.role_permission` | none | Access rules | Administrators | Permanent | – |
| `identity.password_token` | hashed one-time token, its account, who created it | Password links | Nobody (hashes only) | 24 h, then unusable | Deleted with the account |
| `organization.service`, `organization.stage` | none | Services and classes | Staff | Permanent | – |
| `organization.registration_service` | which services a registering servant chose | Approving registrations | Administrators | Until the request is decided | Deleted with the account |
| `people.person` | names, display name, status, birth date, address | Members (children, youth, servants as members) | Staff (`members: read`); parents for their own children | While a member; open (service leaders) | `erase-person`: deleted |
| `people.membership` | which person is in which service or stage, from, until | Rosters | Staff | With the person | Deleted with the person |
| `people.guardian_relationship` | which account is a parent of which person, link status | Parent portal access | Administrators; the parent | While the link is approved | Deleted with the person or the account |
| `meetings.meeting_schedule` | none | Recurring meetings | Staff | Permanent | – |
| `meetings.meeting_session` | who opened and closed it, cancel reason (may name children) | Attendance sessions | Staff; cancel reasons only with `attendance: read` | Permanent (totals) | Actors set to null; erased people kept as anonymous counters |
| `meetings.session_event` | actor, note (may name children) | Session history | Staff with `attendance: read` for notes | Permanent | Actor set to null |
| `meetings.attendance` | who was present, who recorded or removed the mark, removal reason (may name children) | Attendance | Staff (`attendance: read`) | Open (service leaders) | Deleted with the person; actors set to null |
| `learning.lesson` | responsible servant, notes (may name children) | Lesson planning | Staff; notes only with `members: read` | Open (service leaders) | Responsible set to null |
| `learning.homework_assignment` | who created it | Homework | Staff; published tasks to the children's parents | Open (service leaders) | Creator set to null |
| `learning.homework_completion` | which child did which task, who reported and undid it | Homework progress | Staff (`members: read`); the child's parents | Open (service leaders) | Deleted with the person; reporters set to null |
| `privacy.data_request` | who it is about, who asked, channel, note, outcome | The office's request log | Administrators | Open (office and adviser) | Subject set to null and described in `erased_subject_*`; closer set to null |
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
- **Access:** every workflow checks its permission (`AccessCheck`) before it reads or writes; parent routes deny
  by default and answer the same 404 for anything that is not the parent's; servant notes and cancel reasons need
  extra permissions.
- **Passwords:** hashed with Symfony's `auto` hasher; set through one-time links valid 24 h; sign-in is throttled.
- **Logs:** no names, emails or children's data in production logs; request ids instead.
- **Production access:** only the named operators, through the provider's console with two-factor sign-in.
- **Fictional data outside production:** development and tests use `app:demo:seed` and fixtures, never copies of
  production.
- **Confidentiality:** every servant and operator signs the church's confidentiality undertaking before getting an
  account.

## Procedures

| Request | Who acts | Deadline | Steps |
| --- | --- | --- | --- |
| **Copy** (Art. 15/20) | Office, with an administrator | One month from receipt | 1. Record it (`POST /api/data-requests`). 2. Check the requester's identity (in person, or a reply from the account's email). 3. `app:privacy:export --person=… \| --account=… --output=…`. 4. Hand the file over safely (in person, or an encrypted email). 5. Delete local copies. 6. Close the request. |
| **Erasure** (Art. 17) | Administrator | One month | 1. Record it. 2. Revoke access first (`app:user:disable`). 3. `app:privacy:erase-person <id>` and/or `app:privacy:erase-account <id>` (`:dry-run` first). 4. Review notes that may name the person (#113). 5. Add the erased ids to the [erasure ledger](#erasure-ledger). 6. Close the request with the outcome. |
| **Rectification** (Art. 16) | Service leader (members), administrator (accounts) | One month | Correct the record in the app; tell the requester. |
| **Objection** (Art. 21) | Office with the adviser | One month | Weigh the church's legitimate interest; record the decision. |
| **Breach** (Art. 33) | Administrator, then the adviser | 72 h after becoming aware | Contain, assess, report to the authority; inform families when the risk is high. |
| **Servant leaves** | Service leader | The same week | `app:user:disable <email>`; take the role. |
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
