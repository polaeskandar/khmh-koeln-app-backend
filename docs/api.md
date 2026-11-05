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

#### `POST /api/auth/password-links`

Public. Body `{token, password}`: sets a new password with the one-time link from the `password_link` email
(`app:user:password-link`). The token travels only in this body; the web app reads it from the link's
`#token=` fragment. Answers 204; the new password signs in, and every session of the account ends.

| Case | Response |
| --- | --- |
| `password` shorter than 10 or longer than 200 characters | 422 `accounts.passwordTooShort {min: 10}` or `validation.tooLong {max: 200}` on `password`; the link stays usable |
| Token missing, unknown, used, expired or replaced by a newer link, or the account is pending or disabled | 422 `auth.resetLinkInvalid`, the same body for each |
| An 11th request from one IP within an hour | 429 `auth.tooManyAttempts` with `Retry-After` |

The password is checked before the link is spent. Spending the link and saving the password happen in one
transaction, so a link works exactly once.

#### `POST /api/auth/register`

Public. Creates an account that waits for an administrator's approval. Its roles come with the approval.

```json
{
  "account": {
    "firstName": "Mariam",
    "lastName": "Hanna",
    "email": "mariam.hanna@example.org",
    "password": "correct horse battery",
    "language": "ar"
  },
  "registration": {"registeredAs": "parent", "note": "Youssef, 3rd Class", "serviceIds": []},
  "privacy": {"accepted": true, "version": "2026-11"}
}
```

| Member | Rules (field error) |
| --- | --- |
| `account.firstName`, `account.lastName` | Required (`accounts.firstNameRequired`, `accounts.lastNameRequired`), ≤80 (`validation.tooLong`); spaces collapsed, stored as the display name "First Last" |
| `account.email` | Required (`accounts.emailRequired`), valid (`accounts.emailInvalid`), ≤180; trimmed and lower-cased |
| `account.password` | 10–200 characters (`accounts.passwordTooShort {min}`, `validation.tooLong`) |
| `account.language` | `en`, `de` or `ar` (`accounts.chooseLanguage`) |
| `registration.registeredAs` | `parent` or `servant` (`accounts.chooseRegisteredAs`) |
| `registration.note` | Required (`accounts.childrenRequired` for parents, `accounts.serviceDetailsRequired` for servants), ≤1000 |
| `registration.serviceIds` | Servants: at least one active service, each once (`accounts.chooseService`). Ignored for parents. |
| `privacy.accepted` | `true` (`accounts.acceptPrivacy`) |
| `privacy.version` | The current privacy notice version, `2026-11` |

| Result | Answer |
| --- | --- |
| Valid, new email | 202 `{"status": "received"}`, a pending account |
| Valid, the email already has an account (any case, any state) | The same 202, byte for byte; nothing changes |
| A field fails | 422 `checkFields` with every failed field |
| `privacy.version` is not the current one | 409 `accounts.privacyNoticeOutdated` |
| More than 20 registrations from one client within an hour | 429 `auth.tooManyAttempts` + `Retry-After` |

The password is hashed in both 202 cases, so the answer takes as long either way. Signing in before approval
answers 403 `auth.pending`.

#### `GET /api/auth/register/services`

Public. The services a servant can name: `{"items": [{"id", "name"}]}`, active services by name, at most 200.

#### `GET /api/me`

Any signed-in account, parents included. 401 `auth.unauthenticated` without a valid session or token.

| Member | Type |
| --- | --- |
| `account` | `{id, email, displayName, language}`; `language` is `en`, `de` or `ar` |
| `roles` | `[{id, name}]`, by name |
| `permissions` | `[{action, resource}]`, every permission the account's roles grant, once each, by resource and action in the order of the catalog |
| `today` | date |

Nothing else: no password hash, registration, approval or disabled data.

Clients choose the account's areas and navigation from `permissions`, by the same rules the API enforces:

- **Staff app:** a permission on any resource other than `parentPortal`.
- **Parent portal:** `read` on `parentPortal`.
- **Navigation:** for example the users and roles settings for `users: read`.

An account can have both areas; an approved account without roles has neither and sees the no-role view.

A servant (its `members`, `lessons` and `homework` permissions left out):

```json
{
  "account": {
    "id": "0192f3a4-7b1c-7d2e-8f3a-4b5c6d7e8f01",
    "email": "daniel.mikhail@example.org",
    "displayName": "Daniel Mikhail",
    "language": "de"
  },
  "roles": [{"id": "019a3f00-0000-7000-8000-000000000003", "name": "Servant"}],
  "permissions": [
    {"action": "read", "resource": "services"},
    {"action": "create", "resource": "attendance"},
    {"action": "read", "resource": "attendance"},
    {"action": "update", "resource": "attendance"},
    {"action": "delete", "resource": "attendance"}
  ],
  "today": "2026-11-08"
}
```

A parent:

```json
{
  "account": {
    "id": "0192f3a4-7b1c-7d2e-8f3a-4b5c6d7e8f03",
    "email": "mariam.hanna@example.org",
    "displayName": "Mariam Hanna",
    "language": "ar"
  },
  "roles": [{"id": "019a3f00-0000-7000-8000-000000000004", "name": "Parent"}],
  "permissions": [{"action": "read", "resource": "parentPortal"}],
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
| `Recurrence` | `{frequency, interval, weekdays, monthWeek, span: {startsOn, endsOn}}`; see below |

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

**Recurrence** is how a schedule repeats, the same shape in requests and responses:

```json
{"frequency": "monthly", "interval": 2, "weekdays": [6], "monthWeek": 1, "span": {"startsOn": "2026-09-20", "endsOn": null}}
```

- `frequency`: `weekly` (every `interval` weeks, counted from the Monday of `startsOn`'s week, on each of
  `weekdays`) or `monthly` (every `interval` months from `startsOn`'s month, on the `monthWeek`th weekday: 1–4
  are days 1–7, 8–14, 15–21, 22–28; -1 is the last 7 days).
- `weekdays`: ISO numbers, 1 (Monday) to 7 (Sunday); exactly one for monthly.
- `span`: both ends inclusive; `endsOn` may be `null`. Dates have no time zone.
- Field errors, the first broken rule only, under the recurrence's own field (`recurrence.interval` in a schedule
  request), in this order: `interval` (`recurrence.interval`, 1–52), `weekdays` (`recurrence.weekdays`,
  `recurrence.weekdayRange`, `recurrence.monthlyOneDay`), `monthWeek` (`recurrence.monthWeek`,
  `recurrence.monthWeekOnlyMonthly`), `span.endsOn` (`recurrence.endBeforeStart`).
- `tests/Fixtures/recurrence-cases.json` holds golden vectors in this shape for the frontend's port.

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
- In development (`APP_DEBUG=1`) a 500 adds `debug: {class, message}`; production never does.

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
| `validation.invalidChoice` | – | **New.** Field error: the value is not one of the allowed choices (`status=deleted`). |
| `validation.tooMany` | `max` | **New.** Field error: more items than allowed. |
| `validation.tooSmall` | `min` | **New.** Field error: the number is below {min}. |
| `validation.invalidRange` | `max` | **New.** Field error: the end is before the start, or the range is longer than `max` days. |
| `validation.birthDateInFuture` | – | **New.** Field error on `birthDate`: the date is after today. |
| `validation.addressIncomplete` | – | **New.** Field error on `address`: give street, postal code and city, or none of them. |

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
| `people.addNotAllowed` | – | **New.** Adding people needs the members permission. |
| `people.changeNotAllowed` | – | **New.** Changing people needs the members permission. |
| `people.removeNotAllowed` | – | **New.** Ending memberships needs the members permission. |
| `people.firstNameRequired` | – | Enter a first name. |
| `people.lastNameRequired` | – | Enter a last name. |
| `people.editOutsideServices` | `name` | You can only edit people in the services you serve in. {name} is not in any of them. |
| `people.addOutsideServices` | – | You can only add people to the services and stages you serve in. |
| `people.chooseJoinService` | – | Choose the service this person joins. |
| `people.duplicate` | `count` | {count, plural, one {Someone called {name} already exists.} other {# people called {name} already exist.}} Check that this is a different person. |
| `people.alreadyIn` | `name`, `where` | {name} is already in {where}. |
| `people.membershipOutsideServices` | – | You can only change memberships in the services and stages you serve in. |
| `people.membershipEnded` | – | This membership has already ended. |
| `people.searchTooShort` | `min` | **New.** Field error on `q`: type at least {min} letters. |
| `people.moveOutsideStages` | – | You can only move people into stages you serve in. |

### `roles`

| Code | Params | Meaning (English reference text) |
| --- | --- | --- |
| `roles.adminOnly` | – | Only administrators can change roles. |
| `roles.chooseService` | – | Choose the service this role applies to. |
| `roles.alreadyHasRole` | `name` | {name} already has this role. |
| `roles.ownAdminRole` | – | You cannot remove your own administrator role. Ask another administrator. |
| `roles.stageOnlyForServants` | – | **New.** Field error on `stageId`: only servant roles can be narrowed to a stage. |
| `roles.lastAdministrator` | – | **New.** 409: the last active account that can manage roles (`roles: update`) keeps that permission. |
| `roles.nameRequired` | – | **New.** Field error on `name`: enter a name for the role. |
| `roles.unknown` | – | **New.** Field error on `role` or `roleIds`: no role has this name or id. |
| `roles.chooseRole` | – | **New.** Field error on `roleIds`: choose at least one role. |
| `roles.notHeld` | `name` | **New.** 409: {name} doesn't have this role. |
| `roles.accountPending` | – | **New.** 409: an account that waits for approval gets its roles when it is approved. |

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
| `homework.alreadyUndone` | – | **New.** 409: this report was already undone. |
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
| `guardians.accountInactive` | – | **New.** 409: only an approved, enabled account can be linked to a child. |
| `guardians.childInactive` | – | **New.** 409: the person is inactive. |

### `accounts`

| Code | Params | Meaning (English reference text) |
| --- | --- | --- |
| `accounts.firstNameRequired` | – | Enter your first name. |
| `accounts.lastNameRequired` | – | Enter your last name. |
| `accounts.nameRequired` | – | **New.** Field error on `displayName`: enter the name to show for the account. |
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
| `accounts.chooseRegisteredAs` | – | **New.** Field error on `registration.registeredAs`: choose parent or servant. |
| `accounts.chooseLanguage` | – | **New.** Field error on `account.language`: choose English, German or Arabic. |
| `accounts.passwordLinkUnavailable` | – | **New.** 409: password links are only for approved, enabled accounts. |

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

### Health

For the platform's probes. Public, no session needed, and the only answers that are not problems.

#### `GET /api/health`

Liveness. Never touches the database.

```json
{"status": "ok"}
```

#### `GET /api/health/ready`

Readiness: runs `SELECT 1` with a 2-second connect and statement timeout.

200:

```json
{"status": "ok", "checks": {"database": "ok"}}
```

503 when the database does not answer in time:

```json
{"status": "unavailable", "checks": {"database": "unavailable"}}
```

### Account requests

Registrations waiting for a decision. The endpoints need the users permission (`users: read` to list,
`users: update` to approve, `users: delete` to decline); anyone else gets 403 `accounts.adminOnly`.

#### `GET /api/account-requests`

`{items: [AccountRequest], roles: [{id, name}], today}`: the 200 oldest pending accounts (not approved, not
disabled), oldest first, and every role by name for the approval dialog.

| Member | Type |
| --- | --- |
| `account` | `{id, email, displayName, language}` |
| `registeredAt` | instant |
| `registeredAs` | `parent` or `servant` |
| `note` | string or `null` |
| `services` | `[ServiceRef]`, the services a servant named; empty for parents |

Nothing else: no password hash and no privacy fields.

#### `POST /api/account-requests/{id}/approve`

Body `{as: "parent", childIds, roleIds}` or `{as: "servant", roleIds}`. Roles hold everywhere, so a servant
gets roles and nothing else; children come from the people directory (`GET /api/people/directory?q=`). In one
transaction, with the account locked: the roles are given, a parent gets approved guardian links to the
children (`approved_by` = the administrator), the account is approved, and the registration's note, its
`registeredAs` and the services the person named are deleted. Any failure stores nothing. 200
`{id, email, displayName, language}`.

| Field | Codes |
| --- | --- |
| `as` | `validation.invalidChoice` (not `parent` or `servant`) |
| `roleIds` | `roles.chooseRole` (none), `roles.unknown` (an id no role has); duplicates are ignored |
| `childIds` (parents) | `accounts.chooseChild` (none), `validation.tooMany {max: 20}`, `accounts.childNotFound` (unknown or inactive); duplicates are ignored |

404 `notFound.user`; 409 `accounts.alreadyApproved`.

#### `POST /api/account-requests/{id}/decline`

Body `{}`. Deletes the account and everything the person entered, then answers 204; the same email can
register again. The account is locked (`SELECT … FOR UPDATE`) while the decision is made, so two
administrators can't both decide. 404 `notFound.user`; 409 `accounts.alreadyApproved` for an approved
account, which stays.

### Services

A service row:

```json
{
  "id": "0192f3a4-7b1c-7d2e-8f3a-4b5c6d7e8f10",
  "name": "Sunday School",
  "description": null,
  "tone": 1,
  "archived": false,
  "archivedAt": null,
  "memberCount": 24,
  "serviceLevelMemberCount": 0,
  "stages": [
    {"id": "0192f3a4-7b1c-7d2e-8f3a-4b5c6d7e8f20", "name": "3rd Class", "position": 2, "archived": false, "can": {"manageMembers": true}}
  ],
  "can": {"edit": false, "archive": false, "manageStructure": false, "manageSessions": false, "manageMembers": true}
}
```

`stages` are the active stages by position. `memberCount` counts the people on any roster of the service today
(Europe/Berlin), each once; `serviceLevelMemberCount` only those on the service-level roster. The flags follow the viewer's permissions: `edit` is
`services: update`, `archive` `services: delete`, `manageStructure` `stages: update`, `manageSessions`
`sessions: update`, `manageMembers` `members: update`.

#### `GET /api/services?status=active|archived|all`

`services: read`. `{items: [service row], can: {create}}`, by name; `status` defaults to `active`, anything else
→ 422 field error `status`: `validation.invalidChoice`.

#### `GET /api/services/{id}`

`services: read`. One service row; 404 `notFound.service`.

#### `POST /api/services`

`services: create` (403 `services.adminOnlyCreate`). Body `{name, description?}`. The name is cleaned (spaces
collapsed): `services.nameRequired`, `validation.tooLong {max: 120}`; a name any other service has, ignoring case
and spaces → `services.nameTaken {name}`, or `services.nameTakenArchived {name}` when that service is archived.
The description is trimmed, empty → `null`, ≤2000. The service gets the least-used tone. 201 with `Location` and
the body of its `GET`.

#### `PATCH /api/services/{id}`

`services: update`. Body `{name?, description?}`; a member left out keeps its value, and `description: null`
clears the description. Same rules as `POST`, also for archived services. 200 with the service row.

### Stages

#### `GET /api/services/{id}/stages`

`services: read`. `{service: ServiceRef, stages: [{id, name, description, position, memberCount}],
archivedStages: [{id, name, archivedAt}], can: {manageStructure}}`; stages by position; `memberCount` counts the
stage's roster today. `manageStructure` is `stages: update` on an active
service, else `false`. 404 `notFound.service`.

#### `POST /api/services/{id}/stages`

`stages: create`. Body `{name, description?}`: `stages.nameRequired`, `validation.tooLong {max: 80}` (description
500); a name another stage of this service has, ignoring case and spaces → `stages.nameTaken {name}` or
`stages.nameTakenArchived {name}`. The stage goes after the active stages and before the archived ones. An archived
service → 409 `services.archivedNoStageChanges {name}`. Writes to one service's stages run one at a time (the
service row is locked). 201 with `Location` and the body of its `GET`.

#### `GET /api/stages/{id}`

`services: read`. `{id, name, description, position, archived, memberCount, service: ServiceRef, can:
{manageStructure, manageMembers}}`; `memberCount` counts the stage's roster today. Both flags are `false` on an archived service; `manageMembers` (`members: update`) is also
`false` on an archived stage. 404 `notFound.stage`.

#### `PATCH /api/stages/{id}`

`stages: update`. Body `{name?, description?}`; a member left out keeps its value, `description: null` clears the
description, and the position stays. Same rules as `POST`. The stage is read once its service is locked, so an
edit that waited for another one works on the committed stage. 200 with the stage.

### People directory

#### `GET /api/people/directory?q=`

`members: read`, so every staff role and no parent. A church-wide name search for pickers (a visitor on the
attendance board, a child when approving a parent): it crosses services on purpose and returns only names and
current groups, nothing else about the person (D7).

`{items: [{id, firstName, lastName, displayName, groups: [{service: ServiceRef, stage: StageRef | null}]}]}`, at
most 20, active people by last and first name. `groups` are the person's current memberships.

`q` is folded (lower case, accents and Arabic harakat removed) and split into words; every word has to appear in
the first, last or display name. Fewer than 2 letters → 422 field error `q`: `people.searchTooShort {min: 2}`.
`%` and `_` match themselves.

### People

#### `GET /api/people?status=active|inactive|all&serviceId=&stageId=&q=&page=`

`members: read`. `{today, items: [PersonRow], page, pageSize: 25, total, can: {add}}`, by last, then first name
(German order). A `PersonRow` is `{person: PersonView, placements: [Placement], pastPlacements: [Placement],
can: {edit}}`; a `Placement` is `{membershipId, service: ServiceRef, stage: StageRef | null, startsOn, endsOn}`,
current ones (`endsOn` null or from today) under `placements`, ended ones under `pastPlacements`. Roles hold
everywhere, so every person is listed.

- `status` defaults to `active`; anything other than `active`, `inactive` or `all` → 422 `validation.invalidChoice`.
- `stageId` keeps the stage's roster today; `serviceId` everyone currently in the service, at any stage or the
  service level. With `stageId`, `serviceId` is ignored. Unknown → 404 `notFound.stage` / `notFound.service`; an id
  that is not a UUID → 422 `validation.invalidType` on that filter.
- `q`: every word (folded like the directory search) appears in the first, last or display name.
- `page` from 1; `page=0` → 422 `validation.tooSmall {min: 1}`. The page costs the same number of statements
  whatever its size.
- `can.add` is `members: create`; `can.edit` is `members: update`.

A `PersonView` is `{id, firstName, lastName, displayName, status}`; a `MembershipView` is `{id, serviceId,
stageId, startsOn, endsOn}`.

#### `POST /api/people`

`members: create` (403 `people.addNotAllowed`). Body `{firstName, lastName, displayName?, placement?: {serviceId,
stageId?}, confirmDuplicate?}`. Names are cleaned first (spaces collapsed): `people.firstNameRequired`,
`people.lastNameRequired`, `validation.tooLong {max: 80}` (display name 120); blank first and last names are
reported together. A placement puts the person on a roster from church-today: `placement.serviceId`
`meetings.chooseService` (unknown) or `meetings.serviceArchived {name}`; `placement.stageId`
`meetings.chooseStage` (unknown or another service's) or `meetings.stageArchived {name}`. Without a placement the
person is on no roster.

Anyone with the same cleaned first and last name, ignoring case and display names, in any status, is a namesake:
409 `people.duplicate {count, name}` with `candidates` (the directory's items for the active namesakes), unless
`confirmDuplicate: true`. The person and the membership are written in one transaction. 201 `{person:
PersonView, membership: MembershipView | null}`.

#### `PATCH /api/people/{id}`

`members: update` (403 `people.changeNotAllowed`). Body `{firstName?, lastName?, displayName?}`; a member left out
keeps its value, `""` clears the display name. Same name rules, no namesake check. 200 `PersonView`; 404
`notFound.person`.

#### `POST /api/people/{id}/memberships`

`members: update` (403 `people.changeNotAllowed`). Body `{serviceId, stageId?}`: puts the person on that roster
from church-today. `serviceId` `meetings.chooseService` or `meetings.serviceArchived {name}`; `stageId`
`meetings.chooseStage` or `meetings.stageArchived {name}`. A current membership on the same roster → 409
`people.alreadyIn {name, where}` (also when two requests race into the unique index); rosters of several stages at
once are fine. 201 `{id, personId, service: ServiceRef, stage: StageRef | null, startsOn, endsOn: null}`; 404
`notFound.person`.

#### `POST /api/memberships/{id}/end`

`members: delete` (403 `people.removeNotAllowed`). Body `{}`. The membership row is locked; one that started
before today ends yesterday (Europe/Berlin), so past rosters keep the person; one that started today is deleted.
Allowed in archived services and stages. 204; 404 `notFound.membership`; already ended → 409
`people.membershipEnded`. There is no `DELETE` route.

### Schedules

A `ScheduleSummary`:

| Member | Type |
| --- | --- |
| `id`, `name`, `description`, `location` | the schedule's details |
| `service`, `stage` | `ServiceRef`, `StageRef \| null` |
| `recurrence` | `Recurrence` |
| `startTime`, `endTime` | `HH:MM`, `endTime` may be `null` |
| `rosterMode` | `expected` or `open` |
| `active` | boolean |
| `generating` | active, not past `endsOn`, and its service and stage are not archived |
| `nextDates` | up to 3 `{date, startTime, status, sessionId}` from today (400-day horizon): sessions `scheduled` or `active` and planned dates without a session (`status: planned`, `sessionId: null`); canceled and completed dates are skipped; empty unless `generating` |
| `sessionCount` | sessions of the schedule, every status |
| `createdAt`, `updatedAt` | instants |
| `can` | `{manage}`: `sessions: update` |

#### `GET /api/schedules?serviceId=&stageId=`

`services: read`. `{today, items: [ScheduleSummary]}`, active and inactive, by name. The filters take ids
(`validation.invalidType` otherwise).

#### `GET /api/schedules/{id}`

`services: read`. `{today, schedule: ScheduleSummary}`; 404 `notFound.schedule`.

### Sessions

A `SessionView`: `{id, scheduleId, occurrenceDate, title, service: ServiceRef, stage: StageRef | null, date,
startTime, endTime, startsAt, endsAt, location, status, rosterMode, cancelReason, can: {manage, record,
reopen}}`. Title, place, roster mode and times are the schedule's; `startsAt`/`endsAt` are the instants in
Europe/Berlin with their offset (`+01:00` in winter, `+02:00` in summer). `cancelReason` is `null` unless the
viewer may read attendance (`attendance: read`), because it may name children (D7). `manage` and `reopen` are
`sessions: update`, `record` is `attendance: create`.

#### `GET /api/sessions/{id}`

`services: read`. `{today, session: SessionView, schedule: {id, name, recurrence, active}, history}`; 404
`notFound.session`. `history` holds every event, oldest first, as `{type, at, actor: AccountRef, byGenerator,
note}`. `actor: null` is the generator when `byGenerator`, otherwise a former account. `note` follows the same
rule as `cancelReason`.

#### `POST /api/sessions/{id}/open`, `/close`, `/reopen`

Each answers 200 `{id, status, openedAt, closedAt, closedBy: AccountRef | null}`, runs in the session write lock
and appends `opened`, `closed` or `reopened` to the history only when something changed, so a phone's retry is
harmless. 404 `notFound.session`.

- **Open** (`attendance: create`): `scheduled` → `active` from the session's date in Europe/Berlin on (also
  late). Before that day → 409 `meetings.opensOnTheDay`; already `active` → 200 unchanged; `canceled` → 409
  `meetings.sessionCanceled`; `completed` → 409 `meetings.attendanceClosed`.
- **Close** (`attendance: create`): `active` → `completed` with `closedAt`/`closedBy`; already `completed` → 200
  unchanged; otherwise 409 `meetings.onlyActiveCanClose`.
- **Reopen** (`sessions: update`, else 403 `meetings.reopenLeadersOnly`): body `{reason?}` (trimmed, blank →
  null, ≤200: `validation.tooLong`), kept as the event's note. `completed` → `active`, `closedAt`/`closedBy`
  cleared, `openedAt` kept; otherwise 409 `meetings.onlyCompletedCanReopen`.

### Attendance marks

A `Mark` is `{id, sessionId, personId, recordedAt, recordedBy: AccountRef | null}`.

#### `PUT /api/sessions/{id}/attendance/{personId}`

`attendance: create`. Body `{}`. Marks the person present while the session is `active`: 201 with the new `Mark`,
recorded by the caller; a repeat → 200 with the unchanged mark (same id, time and recorder), so retries are
harmless. Anyone active can be marked, on the roster or not (visitors, other groups, open rosters).

#### `DELETE /api/sessions/{id}/attendance/{personId}`

`attendance: delete`. Body `{reason?}`: trimmed, blank → `null`, at most 200 characters (`validation.tooLong`). The
current mark gets the remover, time and reason and stays in the history; 204, also when there is no current mark.
A later `PUT` creates a new mark.

Both run with the session row locked (`SELECT … FOR UPDATE`), checking the session, then the person, then the
current mark: `scheduled` → 409 `attendance.openFirst`; `completed` → 409 `attendance.closedReopen`; `canceled` → 409
`meetings.sessionCanceled`; an unknown session → 404 `notFound.session`; an unknown person → 404
`notFound.person`; marking an inactive person → 409 `attendance.personInactive {name}` (removing ignores the
person's status). A refused call writes nothing.

#### `GET /api/attendance/overview`

`attendance: read`. The staff home until the dashboard (#108): `{today, sessions, earlier, closed, openCount}`. A
card is the session's `SessionView` plus `openedAt`, `closedAt`, `closedBy: AccountRef | null` and `counts:
Counts`. Sessions of archived services are left out.

- `sessions`: today's sessions of every status, by start time, service name and stage position.
- `earlier`: sessions still `scheduled` or `active` from 21 days ago to yesterday, oldest first.
- `closed`: the 6 most recently closed sessions, newest first.
- `earlier` and `closed` are empty without `attendance: create`.
- `openCount`: the `active` cards in `sessions` and `earlier`, for the badge.

The cards cost the same number of statements whatever their number.

### The meeting a lesson is for

Lesson reads carry a `meeting`, one of:

| `kind` | Members | When |
| --- | --- | --- |
| `none` | `date` | The lesson has a date but no schedule. |
| `planned` | `date`, `startTime`, `title`, `createdOn` | The schedule is active and meets that day, and the session isn't created yet; `createdOn` is the day before, when the generator creates it. |
| `session` | `id`, `status`, `date`, `startTime`, `endTime`, `title`, `location`, `cancelReason` | The session exists (it wins over everything else), canceled ones included. |
| `not-on-schedule` | `date`, `title \| null` | The schedule is inactive or doesn't meet that day. |

Writes send `{scheduleId, date}`, `{sessionId}` (stored as its schedule and date) or `{date}` alone. Field errors
on `meeting`: nothing chosen → `lessons.chooseWhen`; another stage's schedule or session, or a canceled session
as a new link (chosen by `sessionId` or by `scheduleId` and `date`) → `lessons.chooseMeeting`; an impossible date → `lessons.chooseDate`; no meeting that day →
`lessons.noMeetingThatDay {name}`; another non-archived lesson already there → `lessons.meetingHasLesson {title}`.
An existing canceled link stays valid when the lesson is edited.

Lesson preparers (`responsibleId` choices) are the active accounts whose roles grant `lessons: update`, by name
in German collation.

