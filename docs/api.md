# API contract

This is the contract between this API and its clients: the web app, and later the iOS and Android apps.
The frontend builds against it from day 1 (its typed client and MSW mocks implement it), so it is written
before the code. Every endpoint issue adds its own section here as its first PR (D5), using the
[template](#endpoint-section-template).

Words: **church-today** is the current calendar date in Europe/Berlin. A **code** is a stable,
dot-separated identifier such as `services.hasHistory`; the apps show the text for it in the person's
language, and the API never sends sentences.

## Conventions

### Base URL and formats

- The API lives under `/api` on the app's own origin (D2): `https://app.khmk-koeln.com/api`. In development
  the web app runs on `http://localhost:3000` and the API on `http://localhost:8000/api`.
- Request and response bodies are JSON (`application/json`, UTF-8). Errors are `application/problem+json`.
  Member names are camelCase. Objects are unordered: clients never depend on member order, and tests compare
  decoded JSON. Examples here are pretty-printed; the wire form has no whitespace.
- Every `/api` response carries `Cache-Control: no-store` and `X-Content-Type-Options: nosniff` (D6),
  errors included.
- `null` is sent, not omitted: a documented member is always present.

### Values

| Kind | Format | Example |
| --- | --- | --- |
| Id | UUIDv7, lower case, created by the API | `"0192f3a4-7b1c-7d2e-8f3a-4b5c6d7e8f90"` |
| Calendar date | `YYYY-MM-DD` in Europe/Berlin | `"2026-11-08"` |
| Time of day | `HH:MM`, 24 hours, Europe/Berlin | `"18:30"` |
| Instant | RFC 3339 with the Berlin offset, whole seconds | `"2026-11-08T12:00:00+01:00"` |
| Language | `en`, `de` or `ar` | `"de"` |

- A path `{id}` that is not a UUID gets the same 404 as an unknown id (`notFound.<thing>`).
- Dates and times are never converted through a time zone; a schedule "Sundays 12:00" stays 12:00 across
  daylight saving time. Only instants carry an offset.
- Read models that depend on the date carry `today` (church-today), so clients never compute it from the
  device clock.

### Permission flags

Read models carry `can: {…}` with one boolean per action the viewer may take on that item, for example
`"can": {"edit": true, "archive": false}`. The flags only decide what the apps show. The API checks every
request again and answers 403 when the role does not allow it.

### Lists

Lists answer `{"items": [...]}`, plus the members their section documents (`today`, tab counts, `can`).
Paged lists add `page` (from 1), `pageSize` and `total`.

## Methods

| Method | Use | Success |
| --- | --- | --- |
| `GET` | Reads. Never changes anything. | 200 |
| `POST` on a collection | Creates. | 201 with `Location` and a body with at least `id`. Registration answers 202. |
| `PATCH` | Changes only the members sent. A nested object or array that is sent replaces the old value whole. Sections that document a complete body say so. | 200 with the changed resource |
| `PUT` | Sets a state idempotently, e.g. `PUT /api/sessions/{id}/attendance/{personId}`. Repeating it changes nothing. | 201 when it created something, else 200 |
| `POST` on a sub-path | Runs an action: `/archive`, `/restore`, `/approve`, `/end`. | 200 with the resource, or 204 |
| `DELETE` | Deletes, where the section allows it. | 204 |

Unsafe methods (`POST`, `PUT`, `PATCH`, `DELETE`) always send a JSON body with
`Content-Type: application/json` (a `; charset=utf-8` parameter is fine); without a body they send `{}`.
A missing body, another media type (`text/plain`, form data, multipart) or no `Content-Type` → 415
`invalidRequest`. Malformed JSON → 400 `invalidRequest`. A method the route does not have → 405
`invalidRequest`.

```http
POST /api/services HTTP/1.1
Content-Type: application/json
Origin: https://app.khmk-koeln.com

{"name":"Choir","description":null}
```

```http
HTTP/1.1 201 Created
Location: /api/services/0192f3a4-7b1c-7d2e-8f3a-4b5c6d7e8f90
Content-Type: application/json
Cache-Control: no-store

{"id":"0192f3a4-7b1c-7d2e-8f3a-4b5c6d7e8f90", …}
```

## Authentication

Two schemes, one per kind of client (D1). Both reach the same endpoints, codes and permission checks.

| Client | Scheme | Available |
| --- | --- | --- |
| Web app (browser, same origin) | HttpOnly session cookie | From launch |
| iOS and Android apps (`capacitor://localhost`, `https://localhost`) | `Authorization: Bearer <token>` | After the MVP (#83) |

The web app never sends an `Authorization` header and never sees a token: it sends requests with
`credentials: "include"` and lets the browser carry the cookie. Nothing about the session is kept in web
storage.

A request with `Authorization: Bearer …` is authenticated by the token alone: the API ignores any cookie on
it and creates no session.

### Cookie session (web)

Sessions are stored in PostgreSQL and last `SESSION_LIFETIME` (30 days). The production cookie is
`__Host-khmk_session; Path=/; Secure; HttpOnly; SameSite=Lax`, without `Domain`. Signing in again rotates
the session id.

A session ends with sign-out, and also when the account is disabled or its password changes. Its next
request gets 401 `auth.unauthenticated`. A new display name or language does not end it. Roles are read on
every request, so a changed role takes effect without signing in again.

**Origin check.** An unsafe request whose `Origin` is not the app's origin (`FRONTEND_URL`) or a configured
development origin → 403 `forbidden`, before anything else runs (sign-in throttling included). Requests
without `Origin` pass. `GET` is never blocked. Bearer requests skip this check.

#### `POST /api/auth/sign-in`

Public. Body `{email, password}`, both strings. The email is trimmed and compared in lower case.

| Result | Answer |
| --- | --- |
| Right email and password, approved, not disabled | 204 + `Set-Cookie` |
| Body not an object with string `email` and `password` | 400 `invalidRequest` |
| Unknown email or wrong password (identical answers) | 401 `auth.invalidCredentials` |
| Right password, account disabled (checked first) | 403 `auth.disabled` |
| Right password, account waiting for approval | 403 `auth.pending` |
| 5 failures for this email and IP within 15 minutes, or the per-IP limit | 429 `auth.tooManyAttempts` + `Retry-After`, even with the right password |

A wrong password never reveals that an account is pending or disabled. Failures set no cookie.

```http
POST /api/auth/sign-in HTTP/1.1
Content-Type: application/json
Origin: https://app.khmk-koeln.com

{"email":"christine.adel@example.org","password":"correct horse battery"}
```

```http
HTTP/1.1 204 No Content
Set-Cookie: __Host-khmk_session=…; path=/; secure; httponly; samesite=lax
Cache-Control: no-store
```

401:

```json
{
  "status": 401,
  "code": "auth.invalidCredentials",
  "params": {}
}
```

403 pending (`auth.disabled` has the same shape):

```json
{
  "status": 403,
  "code": "auth.pending",
  "params": {}
}
```

429:

```http
HTTP/1.1 429 Too Many Requests
Content-Type: application/problem+json
Retry-After: 840
Cache-Control: no-store

{"status":429,"code":"auth.tooManyAttempts","params":{}}
```

#### `POST /api/auth/sign-out`

Public. Body `{}`. Ends the session and expires the cookie. Answers 204, also without a session.
`GET` → 405 `invalidRequest`, and the session stays.

#### `GET /api/me`

Any signed-in account, parents included. 401 `auth.unauthenticated` without a valid session or token.

| Member | Type |
| --- | --- |
| `id` | id |
| `email` | string |
| `displayName` | string |
| `language` | `en`, `de`, `ar`, or `null` until the person chooses one |
| `roles` | `[{id, role, service: ServiceRef \| null, stage: StageRef \| null}]`, administrator first, then by service and stage name. `role` is `administrator`, `service_leader`, `servant` or `parent`. |
| `today` | date |

Nothing else: no password hash, registration, approval or disabled data.

Clients read the account's areas from `roles`, by the same rules the API enforces:

- **Staff area:** at least one role other than `parent` (`administrator`, `service_leader`, `servant`).
- **Parent portal:** a `parent` role. Administrators are not parents unless they also have it.
- An account can be both. An approved account without roles has neither and sees the no-role view.

A servant of Sunday School › 3rd Class:

```json
{
  "id": "0192f3a4-7b1c-7d2e-8f3a-4b5c6d7e8f01",
  "email": "daniel.mikhail@example.org",
  "displayName": "Daniel Mikhail",
  "language": "de",
  "roles": [
    {
      "id": "0192f3a4-7b1c-7d2e-8f3a-4b5c6d7e8f02",
      "role": "servant",
      "service": {"id": "0192f3a4-7b1c-7d2e-8f3a-4b5c6d7e8f10", "name": "Sunday School", "tone": 1, "archived": false},
      "stage": {"id": "0192f3a4-7b1c-7d2e-8f3a-4b5c6d7e8f20", "name": "3rd Class", "position": 2, "archived": false}
    }
  ],
  "today": "2026-11-08"
}
```

A parent:

```json
{
  "id": "0192f3a4-7b1c-7d2e-8f3a-4b5c6d7e8f03",
  "email": "mariam.hanna@example.org",
  "displayName": "Mariam Hanna",
  "language": null,
  "roles": [
    {"id": "0192f3a4-7b1c-7d2e-8f3a-4b5c6d7e8f04", "role": "parent", "service": null, "stage": null}
  ],
  "today": "2026-11-08"
}
```

### Bearer tokens (iOS and Android, after the MVP)

Planned in #83, not available at launch; this section fixes the shape so the apps can plan for it. Tokens
are opaque random strings, not JWTs. The API stores only their SHA-256 hash and checks the account on every
request, so a disabled account, a password change or a password set from a link revokes all its tokens at
once. Lifetime `API_TOKEN_LIFETIME` (proposed: 90 days). The apps keep the token in the platform's secure
storage.

- `POST /api/auth/tokens {email, password, deviceName}` → 201 `{token, expiresAt}`, with sign-in's codes
  and throttling (400, 401 `auth.invalidCredentials`, 403 `auth.pending`/`auth.disabled`, 429). There is no
  `Location`: a token is only addressable as `current`.
- `DELETE /api/auth/tokens/current` with `{}` → 204, revokes only the token sent. Without a bearer token
  (a session cookie does not count) → 401 `auth.unauthenticated`.
- An expired, revoked or unknown token → 401 `auth.unauthenticated`.
- CORS allows both app origins with `Authorization` and `Content-Type`, without `Allow-Credentials`.

```json
{"token": "kXh2…", "expiresAt": "2027-02-06T12:00:00+01:00"}
```

## Shared shapes

Read models embed these instead of repeating their own variants.

| Shape | Members |
| --- | --- |
| `ServiceRef` | `{id, name, tone, archived}`; `tone` is 1–8, set once when the service is created, and picks its colour |
| `StageRef` | `{id, name, position, archived}`; `position` is the display order within the service, from 0 |
| `AccountRef` | `{id, name} \| null`; `null` means a former account (erased, D6) and the apps show "a former account" |
| `PersonRef` | `{id, firstName, lastName, displayName}`; `displayName` is the everyday name or `null` |
| `LessonRef` | `{id, title, teachingDate}` |
| `Counts` | `{present, rosterSize, rosterPresent, notMarked, notOnRoster}` |

```json
{"id": "0192f3a4-7b1c-7d2e-8f3a-4b5c6d7e8f10", "name": "Sunday School", "tone": 1, "archived": false}
```

```json
{"id": "0192f3a4-7b1c-7d2e-8f3a-4b5c6d7e8f20", "name": "3rd Class", "position": 2, "archived": false}
```

```json
{"id": "0192f3a4-7b1c-7d2e-8f3a-4b5c6d7e8f01", "name": "Daniel Mikhail"}
```

```json
{"id": "0192f3a4-7b1c-7d2e-8f3a-4b5c6d7e8f30", "firstName": "Kirollos", "lastName": "Girgis", "displayName": "Kiro"}
```

```json
{"id": "0192f3a4-7b1c-7d2e-8f3a-4b5c6d7e8f40", "title": "The Good Samaritan", "teachingDate": "2026-11-15"}
```

**Counts** are a session's attendance. Not marked present is not the same as absent.

- `present`: people marked present, visitors included.
- `rosterSize`: people expected (on the roster), or `null` when the schedule's roster mode is `open`.
- `rosterPresent`: people on the roster who are marked present.
- `notMarked`: `rosterSize − rosterPresent`; 0 when the roster is open.
- `notOnRoster`: `present − rosterPresent`.
- Removed marks never count. Erased people stay in the numbers through the session's counters, but no list
  shows them.

14 on the roster, 7 of them and 1 visitor marked:

```json
{"present": 8, "rosterSize": 14, "rosterPresent": 7, "notMarked": 7, "notOnRoster": 1}
```

An open roster with 23 marked:

```json
{"present": 23, "rosterSize": null, "rosterPresent": 0, "notMarked": 0, "notOnRoster": 23}
```

## Problems

Every error is an RFC 9457 problem with `Content-Type: application/problem+json`:

| Member | Content |
| --- | --- |
| `status` | The HTTP status |
| `code` | The stable code the apps translate |
| `params` | Object with the values the text needs (`{}` when none) |
| `fieldErrors` | Only on 422 with field errors, see below |

There is no `type`, `title` or `detail`. RFC 9457 makes them optional, and an absent `type` means
`about:blank`. The API sends no sentences: `code` identifies the problem.

- The apps show the text of `code` with `params`.
- Sections may document extension members at the top level, such as `candidates` on 409
  `people.duplicate`. Clients ignore members they do not know.
- `params` and extension members can contain personal data. They are never logged (D6); logs keep the
  code.
- `params` may carry more members than a text uses (validation constraints add `limit` next to `max`).

409 with params:

```json
{
  "status": 409,
  "code": "services.hasHistory",
  "params": {"name": "Choir", "count": 12}
}
```

409 with an extension member:

```json
{
  "status": 409,
  "code": "people.duplicate",
  "params": {"count": 1, "name": "Mina Hanna"},
  "candidates": [
    {
      "id": "0192f3a4-7b1c-7d2e-8f3a-4b5c6d7e8f31",
      "firstName": "Mina",
      "lastName": "Hanna",
      "displayName": null,
      "groups": [
        {
          "service": {"id": "0192f3a4-7b1c-7d2e-8f3a-4b5c6d7e8f10", "name": "Sunday School", "tone": 1, "archived": false},
          "stage": {"id": "0192f3a4-7b1c-7d2e-8f3a-4b5c6d7e8f20", "name": "3rd Class", "position": 2, "archived": false}
        }
      ]
    }
  ]
}
```

### Field errors

A 422 `checkFields` problem carries `fieldErrors`: one entry per field that failed, keyed by its dot path
(`name`, `recurrence.weekdays`, `materials.2.url` for the third item). Each entry is the first failure of
that field only, as `{code, params}`. The input is never echoed back.

```json
{
  "status": 422,
  "code": "checkFields",
  "params": {},
  "fieldErrors": {
    "name": {"code": "validation.tooLong", "params": {"max": 120, "limit": 120}},
    "materials.2.url": {"code": "materials.url", "params": {}},
    "capacity": {"code": "validation.invalidType", "params": {}},
    "colour": {"code": "validation.unexpectedField", "params": {}}
  }
}
```

A body with the wrong JSON type for a member gives `validation.invalidType` on that member; a member the
endpoint does not accept gives `validation.unexpectedField` on its name. Query parameters use the same
shape, keyed by parameter name (`to`: `validation.invalidRange`).

A 422 without fields uses its own code, such as `auth.resetLinkInvalid`.

### Status codes

| Status | Codes | When |
| --- | --- | --- |
| 400 | `invalidRequest` | Malformed JSON, or a body that is not the documented object |
| 401 | `auth.unauthenticated` | No valid session or token |
| 401 | `auth.invalidCredentials` | Sign-in with a wrong email or password |
| 403 | `forbidden` | The role does not allow it, or a foreign `Origin` |
| 403 | `auth.pending`, `auth.disabled` | Sign-in with the right password |
| 403 | specific codes | Where a section names why, e.g. `services.adminOnlyCreate` |
| 404 | `notFound.<thing>` | Unknown, deleted, or non-UUID id |
| 404 | `notFound.route` | No such route under `/api` |
| 404 | `homework.notAvailable` | Parent portal: anything that is not the parent's, always the same (D10) |
| 405 | `invalidRequest` | The route has no such method |
| 409 | rule codes | A business rule forbids it now, e.g. `stages.hasMembers` |
| 409 | `conflict` | Fallback: a concurrent change hit a database constraint |
| 415 | `invalidRequest` | Unsafe method without a JSON body |
| 422 | `checkFields` | Field errors in `fieldErrors` (`validation.invalidType`, `validation.unexpectedField` and the field codes) |
| 422 | specific codes | Without fields, e.g. `auth.resetLinkInvalid` |
| 429 | `auth.tooManyAttempts` | Sign-in, registration, password links; with `Retry-After` in seconds |
| 429 | `tooManyRequests` | Any other limit; with `Retry-After` in seconds |
| 500 | `internal` | Unexpected error; no message, class or trace |
| 503 | `unavailable` | The API or its database is down for a moment |
| – | `network` | Client only: the request did not reach the API (status 0) |

One example per status:

```json
{"status":400,"code":"invalidRequest","params":{}}
```

```json
{"status":401,"code":"auth.unauthenticated","params":{}}
```

```json
{"status":403,"code":"forbidden","params":{}}
```

```json
{"status":404,"code":"notFound.service","params":{}}
```

```json
{"status":404,"code":"notFound.route","params":{}}
```

```json
{"status":405,"code":"invalidRequest","params":{}}
```

```json
{"status":409,"code":"conflict","params":{}}
```

```json
{"status":415,"code":"invalidRequest","params":{}}
```

```json
{"status":422,"code":"auth.resetLinkInvalid","params":{}}
```

```json
{"status":429,"code":"tooManyRequests","params":{}}
```

```json
{"status":500,"code":"internal","params":{}}
```

```json
{"status":503,"code":"unavailable","params":{}}
```

A response that is not `application/problem+json` (a proxy's HTML error page, for example) has no code; the
client treats it by its status.

## Code catalog

Every code the API sends, by module. Each module has its own heading, so contract PRs for different
modules don't conflict. The English text is a reference for what the code means; the apps own the texts.
`params` are the ICU arguments the text uses.

Adding a code: add it to its module here in the endpoint's contract PR, and add its en, de and ar texts as
`errors.<code>` in a paired frontend PR. A code is never renamed or reused for another meaning; retire it
by leaving it unused.

The prototype's `errors.title.*` and `errors.people.duplicateTitle` are screen titles, not codes.

### General

| Code | Params | Meaning (English reference text) |
| --- | --- | --- |
| `forbidden` | – | Your role does not allow this. |
| `checkFields` | – | Check the highlighted fields. |
| `invalidRequest` | – | The request was not valid. |
| `conflict` | – | **New.** 409 fallback: a concurrent change hit a database constraint. Reload and try again. |
| `tooManyRequests` | – | **New.** 429 outside sign-in: too many requests. `Retry-After` says when to retry. |
| `internal` | – | **New.** 500: unexpected server error. Nothing about it is in the body. |
| `unavailable` | – | **New.** 503: the API or its database is temporarily unavailable. |

### `auth`

| Code | Params | Meaning (English reference text) |
| --- | --- | --- |
| `auth.unauthenticated` | – | **New.** 401: no valid session or bearer token (never signed in, signed out, expired, or the account was disabled or its password changed). |
| `auth.invalidCredentials` | – | **New.** 401 on sign-in: wrong email or password, without saying which. |
| `auth.pending` | – | **New.** 403 on sign-in with the right password: an administrator has not approved the account yet. |
| `auth.disabled` | – | **New.** 403 on sign-in with the right password: the account is disabled. |
| `auth.tooManyAttempts` | – | **New.** 429 on sign-in, registration and password links. `Retry-After` says when to retry. |
| `auth.resetLinkInvalid` | – | **New.** 422: the one-time password link is missing, unknown, used, expired or superseded. |

### `notFound`

| Code | Params | Meaning (English reference text) |
| --- | --- | --- |
| `notFound.service` | – | This service does not exist or was deleted. |
| `notFound.stage` | – | This stage does not exist or was deleted. |
| `notFound.person` | – | This person does not exist or was deleted. |
| `notFound.membership` | – | This membership does not exist or was deleted. |
| `notFound.schedule` | – | This schedule does not exist or was deleted. |
| `notFound.session` | – | This session does not exist or was deleted. |
| `notFound.attendance` | – | This attendance record does not exist or was deleted. |
| `notFound.lesson` | – | This lesson does not exist or was deleted. |
| `notFound.homework` | – | This homework does not exist or was deleted. |
| `notFound.user` | – | This account does not exist or was deleted. |
| `notFound.guardian` | – | This parent link does not exist or was removed. |
| `notFound.request` | – | This request does not exist or was deleted. |
| `notFound.route` | – | **New.** 404: no such route under `/api`. |

### `validation`

| Code | Params | Meaning (English reference text) |
| --- | --- | --- |
| `validation.tooLong` | `max` | Use at most {max} characters. |
| `validation.chooseDate` | – | Choose a date. |
| `validation.invalidDate` | – | Enter a valid date. |
| `validation.chooseTime` | – | Choose a time. |
| `validation.invalidTime` | – | Enter a time like 18:30. |
| `validation.invalidType` | – | **New.** Field error: the value has the wrong JSON type (a string where a number is expected). |
| `validation.unexpectedField` | – | **New.** Field error: the body has a member the endpoint does not accept. |
| `validation.invalidRange` | `max` | **New.** Field error: the end is before the start, or the range is longer than `max` days. |

### `services`

| Code | Params | Meaning (English reference text) |
| --- | --- | --- |
| `services.adminOnlyCreate` | – | Only administrators can create services. |
| `services.adminOnlyArchive` | – | Only administrators can archive services. |
| `services.adminOnlyRestore` | – | Only administrators can restore services. |
| `services.adminOnlyDelete` | – | Only administrators can delete services. |
| `services.nameRequired` | – | Enter a name for the service. |
| `services.nameTaken` | `name` | A service called “{name}” already exists. |
| `services.nameTakenArchived` | `name` | An archived service is already called “{name}”. Restore it instead of creating a new one. |
| `services.alreadyArchived` | `name` | {name} is already archived. |
| `services.notArchived` | `name` | {name} is not archived. |
| `services.takingAttendance` | `name` | {name} is taking attendance right now. Close the open sessions first. |
| `services.hasHistory` | `name`, `count` | {name} has meeting history ({count, plural, one {# session} other {# sessions}}). Archive it instead of deleting it. |
| `services.archivedNoStageChanges` | `name` | {name} is archived. Restore it before changing its stages. |

### `stages`

| Code | Params | Meaning (English reference text) |
| --- | --- | --- |
| `stages.nameRequired` | – | Enter a name for the stage. |
| `stages.nameTaken` | `name` | This service already has a stage called “{name}”. |
| `stages.nameTakenArchived` | `name` | An archived stage is already called “{name}”. Restore it instead. |
| `stages.reorderStale` | – | The stages changed while you were sorting them. Reload the page and try again. |
| `stages.alreadyArchived` | `name` | {name} is already archived. |
| `stages.notArchived` | `name` | {name} is not archived. |
| `stages.takingAttendance` | `name` | {name} is taking attendance right now. Close the session first. |
| `stages.hasHistory` | `name`, `count` | {name} has meeting history ({count, plural, one {# session} other {# sessions}}). Archive it instead of deleting it. |
| `stages.hasMembers` | `count`, `name` | {count, plural, one {# person is} other {# people are}} still in {name}. Move them to another stage first, or archive the stage. |

### `meetings`

| Code | Params | Meaning (English reference text) |
| --- | --- | --- |
| `meetings.chooseService` | – | Choose a service. |
| `meetings.serviceArchived` | `name` | {name} is archived. |
| `meetings.chooseStage` | – | Choose a stage of this service. |
| `meetings.stageArchived` | `name` | {name} is archived. |
| `meetings.scheduleNameRequired` | – | Enter a name, for example “Sunday class”. |
| `meetings.endAfterStart` | – | The meeting has to end after it starts. |
| `meetings.chooseRosterMode` | – | Choose who is expected. |
| `meetings.serviceArchivedRestoreFirst` | `name` | {name} is archived. Restore it first. |
| `meetings.stageArchivedRestoreFirst` | `name` | {name} is archived. Restore the stage first. |
| `meetings.scheduleHasSessions` | `count` | {count, plural, one {# session was} other {# sessions were}} already created from this schedule. Deactivate it instead: no new sessions are created and the history stays. |
| `meetings.sessionCanceled` | – | This session was canceled. |
| `meetings.attendanceClosed` | – | Attendance for this session is closed. Reopen it to make corrections. |
| `meetings.opensOnTheDay` | – | Attendance opens on the day of the session. |
| `meetings.onlyActiveCanClose` | – | Only a session that is taking attendance can be closed. |
| `meetings.reopenLeadersOnly` | – | Only service leaders and administrators can reopen a closed session. |
| `meetings.onlyCompletedCanReopen` | – | Only a completed session can be reopened. |
| `meetings.onlyScheduledCanCancel` | – | Only a session that has not started can be canceled. |
| `meetings.sessionEndAfterStart` | – | The session has to end after it starts. |
| `meetings.onlyScheduledCanMove` | – | Only a session that has not started can be moved. |
| `meetings.chooseFutureTime` | – | Choose a time in the future. |
| `meetings.titleRequired` | – | Enter a title. |
| `meetings.noMeetingOnDate` | – | The schedule has no meeting on that date. |
| `meetings.scheduleInactive` | – | This schedule is inactive. Activate it first. |
| `meetings.dateInPast` | – | **New.** The date is before church-today. |

### `recurrence`

| Code | Params | Meaning (English reference text) |
| --- | --- | --- |
| `recurrence.interval` | – | The interval must be between 1 and 52. |
| `recurrence.weekdays` | – | Choose at least one weekday. |
| `recurrence.weekdayRange` | – | Weekdays are numbered 1 (Monday) to 7 (Sunday). |
| `recurrence.monthlyOneDay` | – | A monthly meeting happens on exactly one weekday. |
| `recurrence.monthWeek` | – | Choose the first, second, third, fourth or last weekday of the month. |
| `recurrence.monthWeekOnlyMonthly` | – | Only monthly meetings have a week of the month. |
| `recurrence.endBeforeStart` | – | The end date cannot be before the start date. |

### `attendance`

| Code | Params | Meaning (English reference text) |
| --- | --- | --- |
| `attendance.openFirst` | – | Open attendance for this session first. |
| `attendance.closedReopen` | – | Attendance is closed. Reopen the session to correct it. |
| `attendance.personInactive` | `name` | {name} is inactive. Reactivate them in Members first. |
| `attendance.alreadyPresent` | `name`, `time` | {name} is already marked present ({time}). |
| `attendance.alreadyPresentBy` | `name`, `time`, `by` | {name} is already marked present ({time}, by {by}). |
| `attendance.alreadyRemoved` | – | This entry was already removed. |

### `people`

| Code | Params | Meaning (English reference text) |
| --- | --- | --- |
| `people.firstNameRequired` | – | Enter a first name. |
| `people.lastNameRequired` | – | Enter a last name. |
| `people.editOutsideServices` | `name` | You can only edit people in the services you serve in. {name} is not in any of them. |
| `people.addOutsideServices` | – | You can only add people to the services and stages you serve in. |
| `people.chooseJoinService` | – | Choose the service this person joins. |
| `people.duplicate` | `count` | {count, plural, one {Someone called {name} already exists.} other {# people called {name} already exist.}} Check that this is a different person. |
| `people.alreadyIn` | `name`, `where` | {name} is already in {where}. |
| `people.membershipOutsideServices` | – | You can only change memberships in the services and stages you serve in. |
| `people.membershipEnded` | – | This membership has already ended. |
| `people.moveOutsideStages` | – | You can only move people into stages you serve in. |

### `roles`

| Code | Params | Meaning (English reference text) |
| --- | --- | --- |
| `roles.adminOnly` | – | Only administrators can change roles. |
| `roles.chooseService` | – | Choose the service this role applies to. |
| `roles.alreadyHasRole` | `name` | {name} already has this role. |
| `roles.ownAdminRole` | – | You cannot remove your own administrator role. Ask another administrator. |
| `roles.stageOnlyForServants` | – | **New.** Field error on `stageId`: only servant roles can be narrowed to a stage. |
| `roles.lastAdministrator` | – | **New.** 409: the last active administrator keeps the role. |

### `materials`

| Code | Params | Meaning (English reference text) |
| --- | --- | --- |
| `materials.labelTooLong` | – | Use at most 120 characters for the label. |
| `materials.url` | – | Enter a web address starting with https:// |
| `materials.tooMany` | – | Add at most 10 items. |

### `lessons`

| Code | Params | Meaning (English reference text) |
| --- | --- | --- |
| `lessons.titleRequired` | – | Enter a title, e.g. “The Good Samaritan”. |
| `lessons.chooseMeeting` | – | Choose a meeting of this stage. |
| `lessons.chooseDate` | – | Choose a date. |
| `lessons.noMeetingThatDay` | `name` | {name} does not meet on that date. |
| `lessons.chooseWhen` | – | Choose when the lesson will be taught. |
| `lessons.meetingHasLesson` | `title` | This meeting already has a lesson: “{title}”. Edit that one, or pick another date. |
| `lessons.chooseResponsible` | – | Choose a servant of this stage. |
| `lessons.helpersInStage` | – | Everyone helping must serve in this stage. |
| `lessons.prepareStageOnly` | – | Only servants of this stage and service leaders can prepare its lessons. |
| `lessons.changeStageOnly` | – | Only servants of this stage and service leaders can change its lessons. |
| `lessons.stageArchived` | `name` | {name} is archived. |
| `lessons.archived` | – | This lesson is archived. |
| `lessons.taughtAfterDate` | – | A lesson is marked as taught on or after its teaching date. |
| `lessons.deleteDraftsOnly` | – | Only draft lessons can be deleted. Archive it instead. |
| `lessons.homeworkPublished` | – | Homework of this lesson was published to parents. Archive the lesson instead. |

### `homework`

| Code | Params | Meaning (English reference text) |
| --- | --- | --- |
| `homework.titleRequired` | – | Enter a title, e.g. “Learn the memory verse”. |
| `homework.instructionsRequired` | – | Write what the parent and child should do. |
| `homework.instructionsTooLong` | – | Use at most 2,000 characters. |
| `homework.dueBeforeLesson` | – | The due date can’t be before the lesson. |
| `homework.changeStageOnly` | – | Only servants of this stage and service leaders can change its homework. |
| `homework.lessonWithoutStage` | – | Homework is assigned to a stage. Add the lesson to a stage first. |
| `homework.assignStageOnly` | – | Only servants of this stage and service leaders can assign its homework. |
| `homework.lessonArchived` | – | The lesson is archived. Restore the lesson first. |
| `homework.archivedNoEdit` | – | Archived homework can’t be edited. |
| `homework.archivedNoPublish` | – | Archived homework can’t be published. Restore it first. |
| `homework.notArchived` | – | Only archived homework can be restored. |
| `homework.onlyPublishedBack` | – | Only published homework can be taken back. |
| `homework.alreadyReported` | – | Parents have already reported it as completed. Archive it instead. |
| `homework.publishedStays` | – | Homework that was published stays in the history. Archive it instead. |
| `homework.ownChildrenOnly` | – | You can only report homework for your own children. |
| `homework.notAvailable` | – | This homework is not available. |
| `homework.otherClass` | `name` | This homework is for another class than {name}’s. |

### `guardians`

| Code | Params | Meaning (English reference text) |
| --- | --- | --- |
| `guardians.adminOnlyGrant` | – | Only administrators can give parents access to a child. |
| `guardians.alreadyHasAccess` | `parent`, `child` | {parent} already has access to {child}. |
| `guardians.adminOnlyApprove` | – | Only administrators can approve parent access. |
| `guardians.adminOnlyRemove` | – | Only administrators can remove parent access. |

### `accounts`

| Code | Params | Meaning (English reference text) |
| --- | --- | --- |
| `accounts.firstNameRequired` | – | Enter your first name. |
| `accounts.lastNameRequired` | – | Enter your last name. |
| `accounts.emailRequired` | – | Enter your email address. |
| `accounts.emailInvalid` | – | Enter an email address like name@example.org. |
| `accounts.emailTaken` | – | There is already an account with this email address. Sign in, or reset the password if you forgot it. |
| `accounts.passwordTooShort` | `min` | Use at least {min, number} characters. |
| `accounts.childrenRequired` | – | Write the names and classes of your children, so the church office can link them to your account. |
| `accounts.serviceDetailsRequired` | – | Write where you serve, so the church office can give you the right access. |
| `accounts.chooseService` | – | Choose the service you serve in. |
| `accounts.chooseServiceForRole` | – | Choose the service for this role. |
| `accounts.acceptPrivacy` | – | Please confirm that you have read the privacy notice. |
| `accounts.adminOnly` | – | Only administrators can approve or decline accounts. |
| `accounts.alreadyApproved` | – | This account has already been approved. |
| `accounts.chooseChild` | – | Choose at least one child. |
| `accounts.childNotFound` | – | One of the chosen children no longer exists or is inactive. |
| `accounts.stageNotInService` | – | This stage doesn’t belong to the chosen service. |
| `accounts.privacyNoticeOutdated` | – | **New.** 409 on registration: the privacy notice version the app showed is not the current one. Reload. |
| `accounts.currentPasswordWrong` | – | **New.** Field error on `currentPassword` (P1, #45). |

### `privacy`

| Code | Params | Meaning (English reference text) |
| --- | --- | --- |
| `privacy.adminOnly` | – | Only administrators can handle data protection requests. |
| `privacy.chooseSubject` | – | Choose the member or account the request is about. |
| `privacy.requestedByRequired` | – | Enter who made the request. |
| `privacy.receivedInFuture` | – | The date can’t be in the future. |
| `privacy.alreadyRequested` | – | You have already asked for your account and data to be deleted. The church office will answer within one month of your request. |
| `privacy.notOpen` | – | This request has already been closed. |
| `privacy.notErasure` | – | Only erasure requests delete data. |
| `privacy.notCopy` | – | This is not a request for a copy. |
| `privacy.subjectGone` | – | The data this request is about no longer exists. |
| `privacy.ownAccount` | – | You can’t erase your own account. Ask another administrator. |
| `privacy.declineReasonRequired` | – | Give the reason. The person must be told why, and that they can complain to the data protection authority. |

### Client only

The API never sends these; the frontend uses them for states it detects itself.

| Code | Params | Meaning |
| --- | --- | --- |
| `network` | – | The request did not reach the API (offline, DNS, TLS). Status 0. |
| `sessionExpired` | – | Shown after a 401 `auth.unauthenticated` on a page that needed a session. |

## Endpoint section template

Each endpoint issue copies this into a module section below, as the first PR of the issue, reviewed by the
frontend developer who builds against it.

````markdown
### `METHOD /api/path/{id}`

Who: which accounts may call it (voter attribute, e.g. `MANAGE_MEMBERS` on the stage).

Request: query parameters or body members, with types, limits and defaults. For `PATCH`, whether the body
is partial (default) or complete.

Response: status and body. Use the shared shapes; list every member and say which can be `null`.

```json
{ "a byte-exact example": "…" }
```

Errors:

| Status | Code | Field | When |
| --- | --- | --- | --- |
| 404 | `notFound.thing` | – | … |
| 422 | `checkFields` | `name`: `validation.tooLong {max}` | … |

New codes: list them, and add them to the code catalog.
````

## Endpoints

Endpoint issues add their sections here, one heading per module: Health, Auth and accounts, Services and
stages, Members, Meetings, Attendance, Lessons, Homework, Parent portal, Guardians, Data protection. The auth
endpoints are under [Authentication](#authentication).
