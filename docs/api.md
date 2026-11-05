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

### What the viewer may do

Read models carry no permission flags. Roles are global, so a permission holds for every item, and
`GET /api/me` lists the account's `permissions` once. The apps decide from them which actions to offer, for
example editing a service for `services: update`. The API checks every request again and answers 403 when the
role does not allow it.

### Lists

Lists answer `{"items": [...]}`, plus the members their section documents (`today`, tab counts).
Paged lists add `page` (from 1), `pageSize` and `total`.

## Methods

| Method | Use | Success |
| --- | --- | --- |
| `GET` | Reads. Never changes anything. | 200 |
| `POST` on a collection | Creates. | 201 with `Location` and a body with at least `id`. Registration answers 202. |
| `PATCH` | Changes only the members sent. A nested object or array that is sent replaces the old value whole. Sections that document a complete body say so. | 200 with the changed resource |
| `PUT` | Sets a state or replaces a resource's editable members whole, idempotently, e.g. `PUT /api/sessions/{id}/attendance/{personId}` or `PUT /api/services/{id}`. Repeating it changes nothing. | 201 when it created something, else 200 |
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
| More than 10 requests from one IP within one fixed hour window | 429 `auth.tooManyAttempts` with `Retry-After` |

The password is checked before the link is spent, and hashed only once the link is valid. Spending the link,
dropping the account's other unused links and saving the password happen in one transaction, so a link works
exactly once. Disabling an account drops its unused links, so enabling it again doesn't bring them back.

#### `POST /api/auth/forgot-password`

Public. Body `{email}` → 202 `{"status":"received"}`, the same bytes for every address. Only an active account
(approved, not disabled) gets a `password_link` email in its language, with a link valid for 1 hour that replaces
any older unused link; unknown, pending and disabled addresses get nothing. Looking the address up, creating the
link and sending the email all happen after the response (`AfterResponse`), so the answer's timing reveals nothing.
The link is used with `POST /api/auth/password-links`; links from `app:user:password-link` still last 24 hours.

| Case | Response |
| --- | --- |
| `email` missing or blank | 422 `accounts.emailRequired` on `email` |
| `email` not an address | 422 `accounts.emailInvalid` on `email` |
| `email` longer than 254 characters, like an account's address | 422 `validation.tooLong {max: 254}` on `email` |
| An 11th request from one IP, or a 4th for one address (case-insensitive), within an hour | 429 `auth.tooManyAttempts` with `Retry-After` |

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
| Valid, new email | 202 `{"status": "received"}`, a pending account and an `email_confirmation` email in its language with a link to `/{language}/confirm-email/#token=…`, valid 7 days |
| Valid, the email has an unconfirmed registration | The same 202, byte for byte; a fresh link that voids the older one |
| Valid, the email already has a confirmed account (any case, any state) | The same 202, byte for byte; an `account_exists` email in that account's language with the sign-in link; nothing else changes |
| A field fails | 422 `checkFields` with every failed field |
| `privacy.version` is not the current one | 409 `accounts.privacyNoticeOutdated` |
| More than 20 registrations from one client within an hour | 429 `auth.tooManyAttempts` + `Retry-After` |

The password is hashed in every 202 case and emails leave after the response, so the answer takes as long either
way. An address gets at most 3 of these emails per hour; further registrations still answer 202 and send
nothing. The registration reaches the office (`GET /api/account-requests`) only after the link is used, and
`app:user:purge-unconfirmed` (daily) deletes registrations left unconfirmed for 7 days whose newest link has
expired too, so a link resent on day 6 still works until day 13. Signing in before approval answers 403
`auth.pending`.

#### `POST /api/auth/email-confirmations`

Public. Body `{token}` from the `#token=` fragment of the confirmation link → 204; the registration now waits
for the office. A missing, unknown, used or expired token → 422 `auth.confirmationLinkInvalid`, the same body
for each. An 11th request from one IP within an hour → 429 `auth.tooManyAttempts` with `Retry-After`.

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

Clients choose the account's areas, navigation and actions from `permissions`, by the same rules the API
enforces:

- **Staff app:** a permission on any resource other than `parentPortal`.
- **Parent portal:** `read` on `parentPortal`.
- **Navigation:** for example the users and roles settings for `users: read`.
- **Actions:** for example a service's edit button for `services: update`, its archive button for
  `services: delete`, and creating a service for `services: create`. Read models carry no permission flags.

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

#### `PUT /api/me/language`

Any signed-in account. Body `{language: en|de|ar}`, the whole resource (the account's language is the only thing
it changes itself) → 200 with the `GET /api/me` body, already showing the new language. Password links and emails use it; other sessions stay signed
in and read it on their next `GET /api/me`.

```json
{"language": "ar"}
```

| Case | Answer |
| --- | --- |
| No session | 401 `auth.unauthenticated` |
| `language` missing, `null` or not `en`, `de`, `ar` | 422 `validation.invalidType` on `language` |
| Any other member (`displayName`, `email`, …) | 422 `validation.unexpectedField` on it; nothing is saved |

#### `POST /api/me/password`

Any signed-in account, staff or parent. Body `{currentPassword, newPassword}` → 204 with a new session cookie: the
session id changes (the old one is gone) and this session stays signed in. Every other session of the account ends
(401 on its next request).

| Case | Answer |
| --- | --- |
| No session | 401 `auth.unauthenticated` |
| `currentPassword` blank or wrong | 422 `accounts.currentPasswordWrong` on `currentPassword`; the password stays |
| `newPassword` shorter than 10 or longer than 200 characters | 422 `accounts.passwordTooShort {min: 10}` or `validation.tooLong {max: 200}` on `newPassword` |
| 5 wrong current passwords for the account within 15 minutes | 429 `auth.tooManyAttempts` + `Retry-After`, even with the right password; only wrong ones count |

### Bearer tokens (iOS and Android)

The Capacitor apps (origins `capacitor://localhost`, `https://localhost`, from `MOBILE_APP_ORIGINS`) can't use
the session cookie, so they sign in for an opaque bearer token; the web keeps the cookie (D1). Tokens are random
strings, not JWTs. The API stores only their SHA-256 hash and checks the account on every request. Disabling the
account (`app:user:disable`), a password set from a link and a password change revoke all its tokens. Lifetime
`API_TOKEN_LIFETIME` (90 days). The apps keep the token in the platform's secure storage and send
`Authorization: Bearer <token>`; the scheme is read in any case (`bearer`, `BEARER`).

A request with a bearer token, or to a path under `/api/auth/tokens`, is stateless: it never reads or sets the
session cookie and skips the Origin check. A password change over a token therefore answers 204 without a new
session; it revokes every token of the account, the one sent included. An expired, revoked or unknown token, or one
of a pending or disabled account, gets 401 `auth.unauthenticated`, even with a valid session cookie.

#### `POST /api/auth/tokens`

Public. Body `{email, password, deviceName?}` (`deviceName` at most 100 characters, else 422
`validation.tooLong`) → 201 `{token, expiresAt}`. The same codes and throttling as `POST /api/auth/sign-in`
(400, 401 `auth.invalidCredentials`, 403 `auth.pending`/`auth.disabled`, 429 `auth.tooManyAttempts` with
`Retry-After`; the attempts count together with cookie sign-ins). No session or cookie, and no `Location`: a
token is only addressable as `current`.

```json
{"token": "kXh2…", "expiresAt": "2027-02-06T12:00:00+01:00"}
```

#### `DELETE /api/auth/tokens/current`

Bearer token required. Body `{}` → 204, revokes only the token sent. Without a bearer token (a session cookie
does not count) → 401 `auth.unauthenticated`.

CORS allows both app origins with `Authorization` and `Content-Type`, without `Allow-Credentials`; they are not
in the cookie Origin allowlist.

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

A body's shape is checked before the caller's rights: a malformed body gets 422 even from a caller who may not use
the endpoint, and a well-formed one gets the endpoint's 403.

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
| `auth.confirmationLinkInvalid` | – | **New.** This confirmation link is invalid or has expired. Register again to get a new one. |

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
| `notFound.role` | – | **New.** This role does not exist or was deleted. |
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
| `validation.tooLarge` | `max` | **New.** Field error: the number is above {max}. |
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
| `meetings.generateNotAfterToday` | – | **New.** Console only: `app:sessions:generate --date` is at most church-today. |
| `meetings.chooseRosterMode` | – | Choose who is expected. |
| `meetings.serviceArchivedRestoreFirst` | `name` | {name} is archived. Restore it first. |
| `meetings.stageArchivedRestoreFirst` | `name` | {name} is archived. Restore the stage first. |
| `meetings.scheduleHasSessions` | `count` | {count, plural, one {# session was} other {# sessions were}} already created from this schedule. Deactivate it instead: no new sessions are created and the history stays. |
| `meetings.scheduleHasLessons` | `count` | **New.** 409: {count, plural, one {# lesson is} other {# lessons are}} planned for this schedule. Deactivate it instead. |
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
| `meetings.titleRequired` | – | **New.** Field error: enter a title for the session. |

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
| `people.personInactive` | `name` | **New.** 409: {name} is inactive. Reactivate them first. |
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
| `materials.url` | – | Enter a web address starting with https:// or http:// |
| `materials.tooMany` | – | Add at most 10 items. |

A link longer than 2,048 characters is `validation.tooLong` `{max: 2048}` on its `url`. A link must have a host
without a user name or password, and no spaces, angle brackets, quotes or control characters. A link without a
label is its own label, and the 120-character limit applies only to a label that is written. Every row error and
`materials.tooMany` are reported together.

### `lessons`

| Code | Params | Meaning (English reference text) |
| --- | --- | --- |
| `lessons.titleRequired` | – | Enter a title, e.g. “The Good Samaritan”. |
| `lessons.chooseMeeting` | – | Choose a meeting of this stage. |
| `lessons.chooseDate` | – | Choose a date. |
| `lessons.noMeetingThatDay` | `name` | {name} does not meet on that date. |
| `lessons.chooseWhen` | – | Choose when the lesson will be taught. |
| `lessons.meetingHasLesson` | `title` | This meeting already has a lesson: “{title}”. Edit that one, or pick another date. |
| `lessons.chooseResponsible` | – | Choose someone who prepares lessons. |
| `lessons.helpersInStage` | – | Everyone helping must serve in this stage. |
| `lessons.prepareStageOnly` | – | Only servants of this stage and service leaders can prepare its lessons. |
| `lessons.changeStageOnly` | – | Only servants of this stage and service leaders can change its lessons. |
| `lessons.stageArchived` | `name` | {name} is archived. |
| `lessons.archived` | – | This lesson is archived. |
| `lessons.taughtAfterDate` | – | A lesson is marked as taught on or after its teaching date. |
| `lessons.deleteDraftsOnly` | – | Only draft lessons can be deleted. Archive it instead. |
| `lessons.homeworkPublished` | – | Homework of this lesson was published to parents. Archive the lesson instead. |

### `privacy`

| Code | Params | Meaning (English reference text) |
| --- | --- | --- |
| `privacy.adminOnly` | – | **New.** Only administrators can see and record data protection requests. |
| `privacy.chooseSubject` | – | **New.** Field error on `subject`: choose the member or the account the request is about. |
| `privacy.requestedByRequired` | – | **New.** Field error: enter who made the request. |
| `privacy.receivedInFuture` | – | **New.** Field error: the request can't have been received after today. |
| `privacy.notOpen` | – | **New.** 409: the request is already closed. |
| `privacy.notErasure` | – | **New.** 409: only erasure requests erase data. |
| `privacy.subjectGone` | – | **New.** 409: neither the member nor the account still exists. |
| `privacy.ownAccount` | – | **New.** 409: an administrator can't erase their own account. |
| `privacy.alreadyRequested` | – | **New.** 409: the account already has an open erasure request from the app. |

### `homework`

| Code | Params | Meaning (English reference text) |
| --- | --- | --- |
| `homework.alreadyUndone` | – | **New.** 409: this report was already undone. |
| `homework.titleRequired` | – | Enter a title, e.g. “Learn the memory verse”. |
| `homework.instructionsRequired` | – | Write what the parent and child should do. |
| `homework.instructionsTooLong` | – | Use at most 2,000 characters. |
| `homework.dueBeforeLesson` | – | The due date can’t be before the lesson. |
| `homework.changeStageOnly` | – | Only servants of this stage and service leaders can change its homework. |
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
| `accounts.currentPasswordWrong` | – | **New.** Field error on `currentPassword`: the current password is not right. |
| `accounts.childrenRequired` | – | Write the names and classes of your children, so the church office can link them to your account. |
| `accounts.serviceDetailsRequired` | – | Write where you serve, so the church office can give you the right access. |
| `accounts.chooseService` | – | Choose the service you serve in. |
| `accounts.chooseServiceForRole` | – | Choose the service for this role. |
| `accounts.acceptPrivacy` | – | Please confirm that you have read the privacy notice. |
| `accounts.adminOnly` | – | Only administrators can approve or decline accounts. |
| `accounts.requestDisabled` | – | **New.** 409: this request's account is disabled. Enable it before approving it, or decline it. |
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

`{items: [AccountRequest], roles: [{id, name}], today}`: the 200 oldest pending accounts with a confirmed email
address (not approved, also when disabled, so they can still be declined), oldest first, with `services` by name,
and every role by name for the approval dialog.

| Member | Type |
| --- | --- |
| `account` | `{id, email, displayName, language}` |
| `registeredAt` | instant |
| `registeredAs` | `parent` or `servant` |
| `note` | string or `null` |
| `services` | `[ServiceRef]`, the services a servant named; empty for parents |

Nothing else: no password hash and no privacy fields.

#### `POST /api/account-requests/{id}/approve`

`users: update` and `roles: update` (it grants roles), else 403 `accounts.adminOnly`. Body `{as: "parent",
childIds, roleIds}` or `{as: "servant", roleIds}`. Roles hold everywhere, so a servant gets roles and nothing
else; children come from the people directory (`GET /api/people/directory?q=`). In one transaction, with the
account locked: the roles are given (a parent also gets the Parent role), a parent gets approved guardian links
to the children (`approved_by` = the administrator), the account is approved, and the registration's note, its
`registeredAs` and the services the person named are deleted. Any failure stores nothing. 200
`{id, email, displayName, language}`. The request is checked before the body, and every field error comes back
at once. After the approval is stored, the account gets an `account_approved` email in its language with the
sign-in link (`{FRONTEND_URL}/{language}/sign-in`); a failed email is logged and the approval stands. Declining
sends nothing.

| Field | Codes |
| --- | --- |
| `as` | `validation.invalidChoice` (not `parent` or `servant`) |
| `roleIds` | `roles.chooseRole` (none), `roles.unknown` (an id no role has); duplicates are ignored, in any letter case |
| `childIds` (parents) | `accounts.chooseChild` (none), `validation.tooMany {max: 20}`, `accounts.childNotFound` (unknown or inactive); duplicates are ignored, in any letter case |

404 `notFound.user`, also for an id that is not a UUID; 409 `accounts.alreadyApproved`; 409
`accounts.requestDisabled` for a disabled request, which can still be declined.

#### `POST /api/account-requests/{id}/decline`

Body `{}`. Deletes the account and everything the person entered, then answers 204; the same email can
register again. The account is locked (`SELECT … FOR UPDATE`) while the decision is made, so two
administrators can't both decide. 404 `notFound.user`, also for an id that is not a UUID; 409 `accounts.alreadyApproved` for an approved
account, which stays.

### Staff accounts

Settings for administrators: staff accounts, their roles and password links. Roles are global (D7), so an
assignment is just the role.

#### `GET /api/staff-accounts`

`users: read`, else 403 `forbidden`. `{items: [{id, displayName, email, disabled, roles: [{id, name}]}], today}`:
approved accounts with a staff role (one that grants a permission outside `parentPortal`) or with no role,
disabled ones last, then by name, at most 500; roles by name. Parent-only and pending accounts are left out.

#### `POST /api/accounts/{id}/roles`

`roles: update`, else 403 `roles.adminOnly`. Body `{roleId}` → 201 `{role: {id, name}}`; the account has the
role's permissions from its next request. 404 `notFound.user`; 422 `roles.chooseRole` (missing) or
`roles.unknown` (no such role) on `roleId`; 409 `roles.alreadyHasRole {name}`, `roles.accountPending`. A refusal
stores nothing.

#### `DELETE /api/accounts/{id}/roles/{roleId}`

`roles: update`, else 403 `roles.adminOnly`. Body `{}` → 204, effective on the account's next request. 404
`notFound.user`, `notFound.role`; 409 `roles.notHeld {name}`, `roles.ownAdminRole` (the viewer would lose
`roles: update` on their own account), `roles.lastAdministrator`. The administration lock that `app:user:disable`
and the erasures take serialises every removal, so two administrators removing each other's role can't leave
nobody to manage roles.

#### `POST /api/accounts/{id}/password-link`

`users: update`, else 403 `forbidden`. Body `{}` → 201 `{expiresAt}`: emails the account a one-time
`password_link` valid for 24 hours (`created_by` = the viewer) that replaces any older unused link, as
`app:user:password-link` does. The link itself is never returned, stored or logged. 404 `notFound.user`; 409
`accounts.passwordLinkUnavailable` for a pending or disabled account.

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
  "memberCount": 14,
  "serviceLevelMemberCount": 2,
  "stages": [
    {"id": "0192f3a4-7b1c-7d2e-8f3a-4b5c6d7e8f20", "name": "3rd Class", "position": 2}
  ]
}
```

`stages` are the active stages by position, then by name ignoring case. `memberCount` counts the people on any
roster of the service today (Europe/Berlin), each once; `serviceLevelMemberCount` only those on the
service-level roster. A membership counts from its first day through its last. Archiving a stage keeps its
memberships open, so its members still count toward the service's `memberCount`.

#### `GET /api/services?status=active|archived|all`

`services: read`. `{items: [service row]}`, by name ignoring case; `status` defaults to `active`, anything else
→ 422 field error `status`: `validation.invalidChoice`.

#### `GET /api/services/{id}`

`services: read`. One service row; 404 `notFound.service`.

#### `GET /api/services/insights?status=active|archived|all`

`services: read`. `{items: [{serviceId, next, trend}]}`, one per service the same `status` lists, in the same
order; anything else → 422 field error `status`: `validation.invalidChoice`. All rows are computed in one batch.

- `next`: `{date, startTime, stage: {id, name, position} | null, status: scheduled | active | planned} | null`,
  the earliest scheduled or active session, or planned date of an active schedule, from today to today+21
  (Europe/Berlin); canceled dates are skipped. `null` when there is none and for archived services.
- `trend`: `{sessions, rate, averagePresent}` over the completed sessions dated today−28 to yesterday. `rate` is
  Σ `rosterPresent` / Σ `rosterSize` over expected-roster sessions (two decimals); `averagePresent` the rounded
  mean `present` over open-roster sessions; each `null` without such sessions.

#### `GET /api/service-leaders`

`services: read`. `{items: [AccountRef]}`, the approved, enabled accounts with the Service leader role, by name.
Roles are global (D7), so the list is the same for every service.

#### `POST /api/services`

`services: create` (403 `services.adminOnlyCreate`). Body `{name, description?}`. The name is cleaned (spaces
collapsed): `services.nameRequired`, `validation.tooLong {max: 120}`; a name any other service has, ignoring case
and spaces → `services.nameTaken {name}`, or `services.nameTakenArchived {name}` when that service is archived.
The description is trimmed, empty → `null`, ≤2000. The service gets the least-used tone. 201 with `Location` and
the body of its `GET`.

#### `PUT /api/services/{id}`

`services: update`. Body `{name, description?}`, the whole editable service: a description left out or `null`
clears it, and a missing or `null` name → `services.nameRequired`. Same rules as `POST`, also for archived
services. 200 with the service row; 404 `notFound.service`.

### Stages

#### `GET /api/services/{id}/stages`

`services: read`. `{service: ServiceRef, stages: [{id, name, description, position, memberCount}],
archivedStages: [{id, name, archivedAt}]}`; stages by position; `memberCount` counts the stage's roster today. Apps offer structure changes for `stages: create`/`stages: update` while
`service.archived` is `false`. 404 `notFound.service`, also for an id that is not a UUID.

#### `PUT /api/services/{id}/stages/order`

`stages: update`, else 403 `forbidden`. Body `{stageIds}`: exactly the service's active stages, each once, in the
new order → 200 with the `GET /api/services/{id}/stages` body in that order. Positions become 0…n−1 in request
order and the archived stages follow in their previous order. With the service row locked (as for adding and
editing stages): a missing, unknown, foreign, archived or repeated id → 409 `stages.reorderStale`, nothing
written; an archived service → 409 `services.archivedNoStageChanges {name}`; 404 `notFound.service`, also for
an id that is not a UUID.
`stageIds` not an array, an id that is not a UUID → 422 `validation.invalidType`; more than 200 →
`validation.tooMany {max: 200}`. Sending the same order again changes nothing.

#### `POST /api/services/{id}/stages`

`stages: create`. Body `{name, description?}`: `stages.nameRequired`, `validation.tooLong {max: 80}` (description
500), every failing field at once; a name another stage of this service has, ignoring case and spaces →
`stages.nameTaken {name}` or
`stages.nameTakenArchived {name}`. The stage goes after the active stages and before the archived ones. An archived
service → 409 `services.archivedNoStageChanges {name}`. Writes to one service's stages run one at a time (the
service row is locked). 201 with `Location` and the body of its `GET`.

#### `GET /api/stages/{id}`

`services: read`. `{id, name, description, position, archived, memberCount, service: ServiceRef}`; `memberCount`
counts the stage's roster today. Apps offer structure
changes for `stages: update` on an active service, and member changes for `members: update` on an active stage of
an active service. 404 `notFound.stage`, also for an id that is not a UUID.

#### `PUT /api/stages/{id}`

`stages: update`. Body `{name, description?}`, the whole editable stage: a description left out or `null` clears it,
a missing or `null` name → `stages.nameRequired`, and the position stays. Same rules as `POST`. The stage is read once its service is locked, so an
edit that waited for another one works on the committed stage. 200 with the stage.

### People directory

#### `GET /api/people/directory?q=`

`members: read`, so every staff role and no parent. A church-wide name search for pickers (a visitor on the
attendance board, a child when approving a parent): it crosses services on purpose and returns only names and
current groups, nothing else about the person (D7).

`{items: [{id, firstName, lastName, displayName, groups: [{service: ServiceRef, stage: StageRef | null}]}]}`, at
most 20, active people by last and first name ignoring case. `groups` are the person's current memberships, in the
order they were created.

`q` is folded (lower case, accents and Arabic harakat removed) and split into words; every word has to appear in
the first, last or display name. Field errors on `q`: fewer than 2 characters besides spaces →
`people.searchTooShort {min: 2}`; more than 100 characters → `validation.tooLong {max: 100}`; bytes that are not
UTF-8 → `validation.invalidType`. `%` and `_` match themselves.

### People

#### `GET /api/people?status=active|inactive|all&serviceId=&stageId=&q=&page=`

`members: read`. `{today, items: [PersonRow], page, pageSize: 25, total}`, by last, then first name
(German order). A `PersonRow` is `{person: PersonView, placements: [Placement], pastPlacements: [Placement]}`; a `Placement` is `{membershipId, service: ServiceRef, stage: StageRef | null, startsOn, endsOn}`,
current ones (`endsOn` null or from today) under `placements`, ended ones under `pastPlacements`. Roles hold
everywhere, so every person is listed.

- `status` defaults to `active`; anything other than `active`, `inactive` or `all` → 422 `validation.invalidChoice
  {choices}`. Every query error comes back at once, except `page`, which is checked after them.
  With the default, inactive people on a roster are left out (a roster itself ignores status, so `memberCount`
  can be higher); `status=all` lists the whole roster.
- `stageId` keeps the stage's roster today; `serviceId` everyone currently in the service, at any stage or the
  service level. With `stageId`, `serviceId` is ignored. Unknown → 404 `notFound.stage` / `notFound.service`; an id
  that is not a UUID → 422 `validation.invalidType` on that filter.
- `q`: every word (folded like the directory search) appears in the first, last or display name; at most 100
  characters (`validation.tooLong {max: 100}`), and text that is not UTF-8 → `validation.invalidType`.
- `page` from 1 to 10000; `page=0` → 422 `validation.tooSmall {min: 1}`, beyond → `validation.tooLarge {max:
  10000}`. The page costs the same number of statements whatever its size.
- Unknown query parameters are ignored.

A `PersonView` is `{id, firstName, lastName, displayName, status}`; a `MembershipView` is `{id, serviceId,
stageId, startsOn, endsOn}`.

#### `POST /api/people`

`members: create` (403 `people.addNotAllowed`). Body `{firstName, lastName, displayName?, placement?: {serviceId,
stageId?}, confirmDuplicate?}`. Names are cleaned first (spaces collapsed): `people.firstNameRequired`,
`people.lastNameRequired`, `validation.tooLong {max: 80}` (display name 120). Every name and placement error
comes back at once. A placement puts the person on a roster from church-today: `placement.serviceId`
`meetings.chooseService` (unknown) or `meetings.serviceArchived {name}`; `placement.stageId`
`meetings.chooseStage` (unknown or another service's) or `meetings.stageArchived {name}`. Without a placement the
person is on no roster.

Anyone with the same cleaned full name (first and last name together, so the split between them doesn't matter),
ignoring case and display names, in any status, is a namesake: 409 `people.duplicate {count, name}` with
`candidates` (the directory's items for the active namesakes, empty without `members: read`), unless
`confirmDuplicate: true`. The person and the membership are written in one transaction. 201 `{person:
PersonView, membership: MembershipView | null}`, without `Location`: there is no `GET /api/people/{id}`, and the
id is `person.id`.

#### `PUT /api/people/{id}`

`members: update` (403 `people.changeNotAllowed`). Body `{firstName, lastName, displayName?}`: a person's
editable members are its name, replaced whole, so a display name left out, `null` or `""` clears it. Same name
rules, no namesake check. 200 `PersonView`; 404
`notFound.person`, also for an id that is not a UUID.

#### `POST /api/people/{id}/deactivate` and `POST /api/people/{id}/reactivate`

`members: update` (403 `people.changeNotAllowed`). Body `{}` → 200 `PersonView` (`{id, firstName, lastName,
displayName, status}`); 404 `notFound.person`. Both are idempotent: a person already in that status comes back
unchanged.

- **Deactivate:** with the person row locked, in one transaction, sets `inactive` and ends every open membership
  as `POST /api/memberships/{id}/end` does (one that started before today ends yesterday, so past rosters and
  attendance keep the person; one that started today is deleted). An inactive person has no open membership and
  is left out of the directory.
- **Reactivate:** sets `active` only; the person is on no roster until someone adds a membership.

#### `POST /api/people/{id}/memberships`

`members: update` (403 `people.changeNotAllowed`). Body `{serviceId, stageId?}`: puts the person on that roster
from church-today. `serviceId` `meetings.chooseService` or `meetings.serviceArchived {name}`; `stageId`
`meetings.chooseStage` or `meetings.stageArchived {name}`. The person is looked up first: 404 `notFound.person`,
also for an id that is not a UUID, and 409 `people.personInactive {name}` for an inactive person (the person row is locked, so this can't race a
deactivation). A current or
not-yet-started open membership on the same roster → 409 `people.alreadyIn {name, where}` (also when two requests
race into the unique index); rosters of several stages at once are fine. 201 `{id, personId, service: ServiceRef,
stage: StageRef | null, startsOn, endsOn: null}`, without `Location`: memberships have no `GET` route.

#### `POST /api/memberships/{id}/move`

`members: update` (403 `people.changeNotAllowed`). Body `{stageId}`: a stage of the membership's service, or
`null` for the service-level roster. Moves the person there from church-today, within the same service. The
person row and then the membership row are locked, the order adding a membership and deactivating the person
take too, so they wait for each other instead of deadlocking; one transaction:

- one that started before today ends yesterday (Europe/Berlin) and a new one starts today, so past rosters and
  attendance stay with the old stage;
- one that started today changes its stage in place (no second row);
- the same stage changes nothing.

200 with the current membership `{id, personId, service: ServiceRef, stage: StageRef | null, startsOn, endsOn:
null}` (the new one, or the changed one). 404 `notFound.membership`; 409 `people.membershipEnded`,
`people.alreadyIn {name, where}` (also when two requests race into the unique index); 422 on `stageId`
`meetings.chooseStage` (unknown or another service's), `meetings.stageArchived {name}`,
`meetings.serviceArchived {name}`. A refusal changes no rows.

#### `POST /api/memberships/{id}/end`

`members: delete` (403 `people.removeNotAllowed`). Body `{}`. The membership row is locked; one that started
before today ends yesterday (Europe/Berlin), so past rosters keep the person; one that started today is deleted.
Allowed in archived services and stages. 204; 404 `notFound.membership`, also for an id that is not a UUID; already ended → 409
`people.membershipEnded`. There is no `DELETE` route.

### Schedules

A `ScheduleSummary`:

| Member | Type |
| --- | --- |
| `id`, `name`, `description`, `location` | the schedule's details; `description` and `location` may be `null` |
| `service`, `stage` | `ServiceRef \| null` (`null` once the service is deleted), `StageRef \| null` |
| `recurrence` | `Recurrence` |
| `startTime`, `endTime` | `HH:MM`, `endTime` may be `null` |
| `rosterMode` | `expected` or `open` |
| `active` | boolean |
| `generating` | active, not past `endsOn`, and its service and stage are not archived |
| `nextDates` | up to 3 `{date, startTime, status, sessionId}` from today (400-day horizon): sessions `scheduled` or `active` and planned dates without a session (`status: planned`, `sessionId: null`); canceled and completed dates are skipped; empty unless `generating` |
| `sessionCount` | sessions of the schedule, every status |
| `rosterSize` | members on the schedule's roster today (Europe/Berlin): its stage's, or for a schedule without a stage the members of the service who are in no stage; `null` for `open` schedules |
| `createdAt`, `updatedAt` | instants |

#### `GET /api/schedules?serviceId=&stageId=`

`services: read`. `{today, items: [ScheduleSummary]}`, active and inactive, by name ignoring case. Each filter
takes an id; anything else, an empty value included, is a 422 field error `validation.invalidType`. `stageId`
returns only the schedules of that stage, not its service's service-wide schedules.

#### `GET /api/schedules/{id}`

`services: read`. `{today, schedule: ScheduleSummary}`; 404 `notFound.schedule`, also for an id that is not a UUID.

#### `POST /api/schedules`

`sessions: create`. Body `{roster: {serviceId, stageId?, mode}, details: {name, description?, location?},
recurrence: Recurrence, times: {startTime, endTime?}}` → 201 `{id, createdSessions}` with `Location`. The schedule is
active; in the same transaction #36's generator creates its sessions for today and tomorrow when they are
occurrences (`createdSessions`).

Field errors: `roster.serviceId` `meetings.chooseService` / `meetings.serviceArchived {name}`; `roster.stageId`
`meetings.chooseStage` / `meetings.stageArchived {name}`; `roster.mode` `meetings.chooseRosterMode`; `details.name`
`meetings.scheduleNameRequired`, `validation.tooLong {max: 120}` (description 500, location 120); `times.startTime`
`validation.chooseTime`, `times.*` `validation.invalidTime`, `times.endTime` `meetings.endAfterStart`;
`recurrence.*` as in Recurrence; `recurrence.frequency` `validation.invalidChoice`, `recurrence.span.startsOn`
`validation.chooseDate`, `recurrence.span.*` `validation.invalidDate`. Once the body is well-formed, the errors of
the roster, the name, the recurrence rules and the times are reported together. The name's spaces are collapsed; an empty description or location is `null`. Each
session copies the schedule's name (as its title), location, times and roster mode when it is created.

#### `PUT /api/schedules/{id}`

`sessions: update`. Body `{details: {name, description?, location?}, recurrence: Recurrence, times: {startTime,
endTime?}, rosterMode}`, the whole editable schedule: a description, location or end time left out, `null` or `""`
is cleared. Same field errors as `POST`, with `rosterMode` `meetings.chooseRosterMode`. The service and stage
can't change (`roster` → `validation.unexpectedField`). The schedule's sessions that are still `scheduled` from
church-today on take the new title, location, times and roster mode; started, closed, canceled and earlier
sessions keep theirs. An active schedule gets its missing sessions for today and tomorrow. 200 `{id,
createdSessions}`; 404 `notFound.schedule`, also for an id that is not a UUID; an archived service or stage → 409
`meetings.serviceArchivedRestoreFirst {name}` / `meetings.stageArchivedRestoreFirst {name}`.

#### `POST /api/schedules/{id}/activate`, `POST /api/schedules/{id}/deactivate` and `DELETE /api/schedules/{id}`

Body `{}`; 404 `notFound.schedule`. Both status changes are idempotent and set `updatedAt` only when they change
something.

- **Activate** (`sessions: update`): 200 `{id, createdSessions}`. 409 `meetings.serviceArchivedRestoreFirst {name}`
  / `meetings.stageArchivedRestoreFirst {name}` while the service or stage is archived. An inactive schedule
  becomes active and the generator creates its missing sessions for today and tomorrow at once
  (`createdSessions`); an active one answers `createdSessions: 0`.
- **Deactivate** (`sessions: update`): 200 `{id}`. Every existing session stays, today's and tomorrow's included;
  the generator creates no new ones.
- **Delete** (`sessions: delete`): 204, only for a schedule that never produced a session of any status (409
  `meetings.scheduleHasSessions {count}`) and has no lessons (409 `meetings.scheduleHasLessons {count}`).

#### `POST /api/schedules/{id}/occurrences/{date}/cancel`

`sessions: update`. Body `{reason?}` as for a session's cancel. Cancels a date of the schedule ahead of time: its
session is created (a `created` event by the caller) and canceled (a `canceled` event) in one transaction, so the
generator leaves the date alone. When the date already has a session, the cancel applies to it; there is never a
second row. 200 `{sessionId}`.

Checks in this order: a malformed or impossible date → 422 `date` `validation.invalidDate`; 404 `notFound.schedule`,
also for an id that is not a UUID; before church-today → 409 `meetings.dateInPast`; not an occurrence → 409
`meetings.noMeetingOnDate`; an inactive schedule → 409 `meetings.scheduleInactive`; an archived service or stage → 409
`meetings.serviceArchivedRestoreFirst {name}` / `meetings.stageArchivedRestoreFirst {name}`; a reason over 200
characters → 422 `reason` `validation.tooLong {max: 200}`; a session that is no longer `scheduled` → 409
`meetings.onlyScheduledCanCancel`. A refused call writes nothing.

### Sessions

A `SessionView`: `{id, scheduleId, occurrenceDate, title, service: ServiceRef, stage: StageRef | null, date,
startTime, endTime, startsAt, endsAt, location, status, rosterMode, cancelReason}`. Title, place, roster mode and times are the session's own (see `POST /api/schedules`; title and place change with
`PUT /api/sessions/{id}/details`). A one-off session has `scheduleId: null`; `startsAt`/`endsAt` are the instants in
Europe/Berlin with their offset (`+01:00` in winter, `+02:00` in summer). `cancelReason` is `null` unless the
viewer may read attendance (`attendance: read`), because it may name children (D7). Apps offer managing and
reopening for `sessions: update` and recording for `attendance: create`.

#### `POST /api/sessions`

`sessions: create`, else 403 `forbidden`. A one-off meeting outside any schedule. Body `{roster: {serviceId,
stageId | null, mode}, title, location | null, startsAt, endsAt | null}` (`roster` as for `POST /api/schedules`)
→ 201 `{id}`. The session is `scheduled` on
the church date of `startsAt`, with a `created` event by the viewer; the generator never touches it, and it is
not listed among the schedules. Two one-offs on one date are fine.

| Field | Codes |
| --- | --- |
| `roster.serviceId` | `meetings.chooseService`, `meetings.serviceArchived {name}` |
| `roster.stageId` | `meetings.chooseStage`, `meetings.stageArchived {name}` |
| `title` | `meetings.titleRequired`, `validation.tooLong {max: 120}` |
| `location` | `validation.tooLong {max: 120}`; blank → `null` |
| `startsAt` | `validation.chooseDate`, `validation.invalidDate` (an instant with offset), `meetings.dateInPast` (a church date before today; later today is fine) |
| `endsAt` | `validation.invalidDate`, `meetings.endAfterStart` (not after `startsAt`, on another church day, or not later on the Europe/Berlin clock to the minute, as within one minute or across the autumn clock change) |
| `roster.mode` | `meetings.chooseRosterMode` (`expected` or `open`) |

#### `PUT /api/sessions/{id}/details`

`sessions: update`, else 403 `forbidden`. Body `{title, location | null}`, the whole resource → 200 `{id}`: the
session's own title and place, with the session row locked and an `edited` event when something changed; the schedule keeps its
name and place. `title` `meetings.titleRequired`, `validation.tooLong {max: 120}`; `location` ≤ 120, blank →
`null`. A canceled session → 409 `meetings.sessionCanceled`; 404 `notFound.session`, also for an id that is not a
UUID.

#### `GET /api/sessions/{id}`

`services: read`. `{today, session: SessionView, state: {id, status, openedAt, closedAt, closedBy: AccountRef |
null}, schedule: {id, name, recurrence, active} | null, history}` (`schedule: null` for a one-off); 404
`notFound.session`, also for an id that is not a UUID. `history` holds every event, oldest first, as `{type, at, actor: AccountRef, byGenerator,
note}`. `actor: null` is the generator when `byGenerator`, otherwise a former account. `note` follows the same
rule as `cancelReason`.

#### `GET /api/dashboard/upcoming`

`services: read` (staff), else 403 `forbidden`; 401 without a session. The staff dashboard's coming week, shaped
like `GET /api/agenda` (`{today, from, to, items}`): tomorrow to today+7 in services that are not archived, with
scheduled, active and canceled sessions and planned dates without a session, in agenda order; completed sessions
are left out. A session after midnight belongs to its own church date, so at 23:50 a session at 00:30 is listed.
Today's sessions are `GET /api/attendance/overview`'s `sessions`, which the dashboard shows above. Greetings,
sentences and grouping stay in the app; the number of SQL statements does not grow with the sessions.

#### `GET /api/agenda?from=&to=&serviceId=&stageId=&scheduleId=`

`services: read`. `{today, from, to, items}` for the church dates `from` to `to`, both included (default today−6 to
today+7, at most 120 days). `items` come by start, then title (German collation):

- `{kind: "session", ...SessionView, counts: Counts | null}`: every session in the range, past ones and canceled
  ones included; `cancelReason` follows `SessionView` (`attendance: read`). `counts` are the attendance board's for
  active and completed sessions, `null` for scheduled and canceled ones.
- `{kind: "planned", scheduleId, title, service: ServiceRef, stage: StageRef | null, date, startTime, endTime,
  startsAt, endsAt, location, rosterMode}` with the schedule's current values: a date of a generating schedule from
  today on that has no session yet. A date with a session, a past date and a date of an inactive or archived
  schedule have none.

The filters take ids and narrow the schedules; `scheduleId` wins over the others. Field errors: `from`/`to`
`validation.invalidDate` (an empty value included); `to` `validation.invalidRange {max: 120}` when it is before `from`
or more than 120 days after it; ids `validation.invalidType` (an empty value included). The items cost the same number
of statements whatever their number.

#### `POST /api/sessions/{id}/open`, `/close`, `/reopen`, `/cancel`

Each answers 200 `{id, status, openedAt, closedAt, closedBy: AccountRef | null}` (its own shape, not a
`SessionView`), runs in the session write lock
and appends `opened`, `closed`, `reopened` or `canceled` to the history only when something changed, so a phone's
retry is harmless (except a repeated cancel, below). 404 `notFound.session`, also for an id that is not a UUID.

- **Open** (`attendance: create`): `scheduled` → `active` from the session's date in Europe/Berlin on (also
  late). Before that day → 409 `meetings.opensOnTheDay`; already `active` → 200 unchanged; `canceled` → 409
  `meetings.sessionCanceled`; `completed` → 409 `meetings.attendanceClosed`.
- **Close** (`attendance: create`): `active` → `completed` with `closedAt`/`closedBy`; already `completed` → 200
  unchanged; otherwise 409 `meetings.onlyActiveCanClose`.
- **Reopen** (`sessions: update`, else 403 `meetings.reopenLeadersOnly`): body `{reason?}` (trimmed, blank →
  null, ≤200: `validation.tooLong` `{max: 200}`), kept as the event's note. `completed` → `active`,
  `closedAt`/`closedBy` cleared, `openedAt` kept; already `active` → 200 unchanged; otherwise 409
  `meetings.onlyCompletedCanReopen`.
- **Cancel** (`sessions: update`): body `{reason?}` (trimmed, blank → null, ≤200: `validation.tooLong` `{max: 200}`),
  kept as `cancelReason` and as the event's note. `scheduled` → `canceled`; otherwise 409
  `meetings.onlyScheduledCanCancel`, a repeat included. The schedule is untouched and the generator never recreates
  the date.

### Attendance marks

A `Mark` is `{id, sessionId, personId, recordedAt, recordedBy: AccountRef | null}`.

#### `GET /api/sessions/{id}/attendance`

`attendance: read`. The attendance board: `{counts: Counts, roster: [BoardRow], notOnRoster: [VisitorRow],
recentlyMarked: [BoardRow]}`. The session itself, its opening and closing (`state`) and its history come from `GET
/api/sessions/{id}`, and the corrections from `GET /api/sessions/{id}/attendance/corrections`, so each read stays
within one service per context.

- A `BoardRow` is `{person: PersonRef, mark: {id, recordedAt} | null}`; a `VisitorRow` is `{person: PersonRef,
  mark: {id, recordedAt}, placements: [{service: ServiceRef, stage: StageRef | null}]}`, the person's current
  placements elsewhere. `PersonRef` is `{id, firstName, lastName, displayName}`.
- `roster`: everyone on the session's roster on its date, in German name order, with their current mark or `null`.
- `notOnRoster`: everyone marked who isn't on that roster (all marks of an open roster), in German name order.
- `recentlyMarked`: the six newest current marks, newest first.
- The lists are filled only for active and completed sessions; scheduled and canceled sessions answer empty lists
  with their counts. The board costs the same number of statements whatever the number of marks.
- 404 `notFound.session` for an unknown session, also for an id that is not a UUID.

#### `GET /api/sessions/{id}/attendance/corrections`

`attendance: read`. `{items: [Correction]}`, the removed marks, oldest removal first. A `Correction` is `{id,
person: PersonRef, recorded: {at, by: AccountRef | null}, removed: {at, by: AccountRef | null}, reason}`; `by` is
`null` for a former account. Costs the same number of statements whatever the number of corrections. 404
`notFound.session` for an unknown session, also for an id that is not a UUID.

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
`notFound.person` (also for ids that are not UUIDs); marking an inactive person → 409 `attendance.personInactive {name}` (removing ignores the
person's status). A refused call writes nothing.

#### `GET /api/attendance/overview`

`attendance: read`. The staff home until the dashboard (#108): `{today, sessions, earlier, closed, openCount}`. A
card is `{session: SessionView, state: {id, status, openedAt, closedAt, closedBy: AccountRef | null}}`, the same
two parts as `GET /api/sessions/{id}`. Sessions of archived services or stages are left out. The counts of the cards come
from `GET /api/attendance/counts`, so each read stays within one service per context.

- `sessions`: today's sessions of every status, by start time, service name (German order) and stage position.
- `earlier`: sessions still `scheduled` or `active` from 21 days ago to yesterday, oldest first.
- `closed`: the 6 most recently closed sessions, newest first (ties by id).
- `earlier` and `closed` are empty without `attendance: create`.
- `openCount`: the `active` cards in `sessions` and `earlier`, for the badge.

The cards cost the same number of statements whatever their number.

#### `GET /api/attendance/counts`

`attendance: read`. `?sessionIds[]=…` (at most 100, else 422 `validation.tooMany {max: 100}` on `sessionIds`;
each a UUID, else `validation.invalidType` on `sessionIds.N`). `{items: [{sessionId, counts: Counts}]}` in the asked
order, each id once; unknown sessions are left out. The counts follow the session's roster on its date and its
current marks, as on the board, and cost the same number of statements whatever the number of sessions.

#### `GET /api/stages/{id}/attendance-history`

`members: read`, else 403 `forbidden`; 404 `notFound.stage`; archived stages stay readable. The stage's 8 latest
completed sessions (of the meetings for that stage) against its members today:

```json
{
  "today": "2026-11-08",
  "sessions": [{"id": "…", "date": "2026-11-01", "startsAt": "2026-11-01T18:30:00+01:00", "present": 12}],
  "rows": [{"person": {"id": "…", "firstName": "Mina", "lastName": "Hanna", "displayName": null}, "cells": ["present"], "presentCount": 1}]
}
```

- `sessions` oldest first, by date and then the meeting's start time; scheduled, active and canceled sessions are
  left out. `present` is the session's
  present count, visitors and erased people included.
- `rows` are today's roster of the stage, by last then first name. Each has one cell per session, in the same
  order: `present` with a current mark (on that session's roster or not), `notMarked` on that session's roster
  without a mark, else `notOnRoster` (for example, joined later). `notMarked` never means absent.
  `presentCount` counts the `present` cells.
- The number of SQL statements does not depend on the number of members or sessions.

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
as a new link (chosen by `sessionId` or by `scheduleId` and `date`) → `lessons.chooseMeeting`; an impossible date
→ `lessons.chooseDate`; no meeting that day → `lessons.noMeetingThatDay {name}`. A schedule meets on a day that
has a session, or on a day its recurrence covers while it is active and neither its service nor its stage is
archived. Another non-archived lesson already there → `lessons.meetingHasLesson {title}`. An existing link stays
valid when the lesson is edited, even if its session was canceled or its schedule no longer meets that day.

Lesson preparers (`responsibleId` choices) are the active accounts whose roles grant `lessons: update`, by name
in German collation.

### Lessons

#### `GET /api/stages/{stageId}/lesson-options?lessonId=`

`lessons: update` (403 `lessons.prepareStageOnly`). Feeds the lesson editor's Meeting and Responsible selects:
`{today, options, servants}`. 404 `notFound.stage`, also for an id that is not a UUID; a `lessonId` that is unknown
or of another stage → 404
`notFound.lesson`; a malformed or empty one → 422 `lessonId` `validation.invalidType`.

- `options`: every date from today−28 (or the edited lesson's teaching date, when earlier) to today+84, both
  included, of the stage's active schedules, by date and time. Each is `{scheduleId, date, startTime, title, state,
  takenBy}`: `title` and `startTime` are the session's own (#199) or, when the date has no session yet, the
  schedule's; `state` is the session's status (`scheduled`, `active`, `completed`, `canceled`) or `planned`. `takenBy` is `{id, title}` of the date's
  non-archived lesson, the edited lesson excepted, or `null`.
- `servants`: the lesson preparers as `AccountRef`.

The options cost the same number of statements whatever the number of lessons.

#### `GET /api/stages/{stageId}/lessons?tab=upcoming|previous|archived&q=`

`lessons: read`. The stage's Lessons tab: `{service: ServiceRef, stage: StageRef, archived, today, tabs: {upcoming,
previous, archived}, servants: AccountRef[], items: LessonRow[]}`; 404 `notFound.stage`, also for an id that is not a
UUID; another `tab` → 422 `validation.invalidChoice`.

- `upcoming` (default): lessons not archived with `teachingDate` from today on, oldest first; `previous`: not
  archived and before today, newest first; `archived`: newest first.
- `q` keeps the rows whose title or passage holds every word, ignoring case and accents. `tabs` counts each tab
  without `q`.
- A `LessonRow` is `{id, title, passage, teachingDate, status, responsible: AccountRef | null, meeting:
  MeetingView, homework: {total, published, drafts}}`. `responsible` is `null` once the account is gone; `homework`
  counts the lesson's tasks that are not archived.
- `archived` is `true` when the stage or its service is archived; apps offer creating a lesson for `lessons:
  create` while it is `false` (`POST` answers 409 `lessons.stageArchived` otherwise). `servants` are the lesson
  preparers.

The rows cost the same number of statements whatever their number.

#### `GET /api/lessons/{id}`

`lessons: read` (403 `forbidden`). The lesson page and the editor: `{service: ServiceRef, stage: StageRef, archived,
today, lesson, homework}`; 404 `notFound.lesson`, also for an id that is not a UUID. Archived lessons stay readable.
`archived` is the stage's or its service's, as in the stage list.

- `lesson` is the stage list's `LessonRow` without `homework`, plus `scheduleId`, `summary`, `memoryVerse`,
  `objectives`, `teachingPoints`, `activities`, `materials`, `notes`, `completedAt` and `archivedAt` (instants in
  Europe/Berlin). `homework` is the row's `{total, published, drafts}`.
- `notes` may name children, so it is `null` unless the caller may read members (`members: read`).
- Apps derive the actions from `GET /api/me`: editing needs `lessons: update` and homework `homework: update`, both
  while `archived` is `false` and the lesson's `archivedAt` is `null`; marking it taught also needs a `ready` lesson
  whose `teachingDate` is `today` or earlier.

#### `POST /api/stages/{stageId}/lessons`

`lessons: create` (403 `lessons.prepareStageOnly`). Body:

```json
{
  "details": {"title": "The Good Samaritan", "summary": null, "passage": "Luke 10:25-37", "memoryVerse": null},
  "meeting": {"scheduleId": "0192f3a4-…", "date": "2026-11-15"},
  "plan": {"objectives": null, "teachingPoints": null, "activities": null, "notes": null,
           "materials": [{"label": "Video", "url": "https://example.org/v"}]},
  "responsibleId": "0192f3a4-…",
  "status": "draft"
}
```

→ 201 `{id, status}`. `meeting` is one of the shapes in "The meeting a lesson is for". The title's spaces are
collapsed and the texts trimmed (empty → `null`); material rows with neither label nor url are dropped.
`status` is `draft` (default) or `ready`. 404 `notFound.stage`; an archived stage or service → 409
`lessons.stageArchived {name}`.

Field errors: `details.title` `lessons.titleRequired`, `validation.tooLong {max: 160}`; `details.summary` 1000,
`details.passage` 200, `details.memoryVerse` 500 and each `plan.*` text 4000 (`validation.tooLong`);
`plan.materials.{i}.label`/`.url` as in `materials`; `meeting` as in "The meeting a lesson is for";
`responsibleId` `lessons.chooseResponsible` when it is missing or not a lesson preparer; `status`
`validation.invalidChoice`. Once the body is well-formed, the errors of the title, the materials, the meeting and the
responsible are reported together. Two saves racing for one meeting → the loser gets `meeting`
`lessons.meetingHasLesson {title}`. 404 `notFound.stage` also for an id that is not a UUID.

#### `PUT /api/lessons/{id}`

`lessons: update` (403 `lessons.changeStageOnly`). The same body, the whole editable lesson: a text or material
list left out, `null` or `""` is cleared, and `meeting` and `responsibleId` are required as on `POST`. The lesson's
own meeting stays valid, and an unchanged `responsibleId` is kept even when that account is no longer a preparer.
`status` left out keeps the lesson's status (a completed lesson stays completed); `draft` or `ready` sets it and
clears a completion. Moving a completed lesson after today → 422 `meeting` `lessons.taughtAfterDate`. Same field
errors as `POST`. 200 `{id, status}`; 404 `notFound.lesson`, also for an id that is not a UUID; an archived lesson
→ 409 `lessons.archived`; an archived stage → 409 `lessons.stageArchived {name}`.

#### `POST /api/lessons/{id}/status`

`lessons: update` (403 `lessons.changeStageOnly`). Body `{status}`: `draft`, `ready` or `completed` (else 422
`status` `validation.invalidChoice`) → 200 `{id, status, completedAt}`. Any status moves to any other; `completed`
needs the teaching date to be church-today or earlier (Europe/Berlin), else 409 `lessons.taughtAfterDate`. It sets
`completedAt`, and leaving `completed` clears it. The same status changes nothing, so retries are harmless. 404
`notFound.lesson`; an archived lesson → 409 `lessons.archived`; an archived stage or service → 409
`lessons.stageArchived {name}`; 404 also for an id that is not a UUID. `completedAt` is an instant in Europe/Berlin.
`PUT`'s `status` (`draft` or `ready`) clears a completion the same way.


### Homework

#### `GET /api/stages/{stageId}/homework`

`homework: read` (403 `forbidden`). The stage's Homework tab and the new editor's lesson choice: `{today, stage:
StageRef, archived, tabs: {published, draft, archived}, lessons: LessonRef[], defaultLessonId, audienceSize, items}`;
404 `notFound.stage`, also for an id that is not a UUID. No instructions, notes or children's names.

- `items`: every task of the stage, newest first by `dueDate`, else the lesson's date, as `{id, title, status,
  dueDate, publishedAt, lesson: {id, title, teachingDate, archived}, counts}`. `counts` is the same as `GET
  /api/homework/{id}` gives (`null` for drafts).
- `tabs` counts the tasks per status; `lessons` are the stage's lessons that are not archived, newest first;
  `defaultLessonId` is the first of them on or after today, else the newest.
- `archived` is the stage's or its service's. Apps offer creating homework for `homework: create` while it is
  `false`, and derive each item's actions as for `GET /api/homework/{id}`.

The items cost the same number of statements whatever their number.

#### `POST /api/lessons/{lessonId}/homework`

`homework: create` (403 `homework.assignStageOnly`). Body `{title, instructions, dueDate?,
resources?: [{label, url?}]}` → 201 `{id, status: "draft"}`: a draft in the lesson's stage, created by the caller.
The title's spaces are collapsed; the instructions are trimmed and keep their line breaks; empty resource rows are
dropped.

Every invalid field comes in one 422: `title` `homework.titleRequired`, `validation.tooLong {max: 120}`;
`instructions` `homework.instructionsRequired`, `homework.instructionsTooLong` (2000); `dueDate`
`validation.invalidDate`, `homework.dueBeforeLesson` (before the lesson's teaching date); `resources`
`materials.tooMany` and `resources.{i}.label`/`.url` as in `materials`. 404 `notFound.lesson`, also for an id that
is not a UUID; an archived lesson
→ 409 `lessons.archived`; an archived stage or service → 409 `lessons.stageArchived {name}`.

#### `GET /api/homework/{id}`

`homework: read` (403 `forbidden`). The homework editor's task: `{today, id, title, status, dueDate, publishedAt,
instructions, resources, minDueDate, lesson: {id, title, teachingDate, passage, memoryVerse, archived}, service:
ServiceRef, stage: StageRef, archived, audienceSize, counts}`; 404 `notFound.homework`, also for an id that is not a
UUID.

- `lesson` holds only those keys: no notes, objectives, teaching points or activities.
- `minDueDate` is the lesson's date, or the saved due date when that is earlier (it stays valid after a move).
- `audienceSize` and `counts` (`null` for a draft) follow "Who a task is for" in `docs/data-model.md`.
- `archived` is the stage's or its service's, as in the lesson list; `lesson.archived` is the lesson's. Apps derive
  the actions from `GET /api/me`: editing needs `homework: update` while neither the task (`status`), its lesson nor
  `archived` is archived; publishing is editing a `draft`; progress needs `members: read`.

#### `PUT /api/homework/{id}`

`homework: update` (403 `homework.changeStageOnly`). The same body, the whole editable task: a `dueDate` left out,
`null` or `""` is cleared, and `resources` left out is an empty list. An unchanged due date stays valid after the
lesson moved past it. The task is locked while it changes; a published task keeps its status, `publishedAt` and
reports. 200 `{id, status}`; 404 `notFound.homework`, also for an id that is not a UUID; an archived task → 409
`homework.archivedNoEdit`; the lesson, stage and service rules as for `POST`.

#### `POST /api/homework/{id}/publish`

`homework: update` (403 `homework.changeStageOnly`). Body `{}` → 200 `{id, status: "published", publishedAt}`
(Europe/Berlin offset). It runs on the locked task row. A draft becomes `published` with `publishedAt` = now, also
when it was published and taken back before; an already published task stays as it is, so a phone's retry changes
nothing. Parents only ever see published tasks. 404 `notFound.homework`, also for an id that is not a UUID; an
archived stage or service → 409 `lessons.stageArchived {name}`; an archived task → 409
`homework.archivedNoPublish`; an archived lesson → 409 `homework.lessonArchived`, in that order.

#### `GET /api/homework/{id}/progress`

`members: read` (403 `forbidden`). The progress page, the only homework read
that names children: `{today, service: ServiceRef, stage: StageRef, homework, lesson: LessonRef, counts, rows}`; 404
`notFound.homework`, also for an id that is not a UUID.

- `homework` is the stage list's item plus `instructions` and `resources`. `counts` is `null` for
  drafts; archived tasks keep counts and rows.
- `rows` are `{person: PersonRef, inStage, done, completedAt, completedBy: AccountRef | null, completedAfterDueDate,
  parentAccounts: AccountRef[]}` for everyone in "Who a task is for", by last name, then first name, in German
  collation: alphabetical, never ranked. `completedBy` is `null` when not done or once the account is gone; an
  undone report is not done.
- No emails, guardian link ids, undo history or lesson notes.

### Parent portal

Parent routes need `parentPortal: read` (else 403 `forbidden`) and only ever read through `VisibleHomework`
("Who a task is for" in `docs/data-model.md`): the viewer's own approved children, published homework, the
lesson's passage and memory verse. Lesson plans, servant notes, attendance, other children and other families
are never part of a parent response.

#### `GET /api/parent/children`

`{today, items: ChildSummary[]}` by first name; `items: []` without approved links. A `ChildSummary` is `{id,
name, fullName, initials, stage, service, toDo, done}`: `name` is the display name, else the first name;
`initials` are the first letters of `name` and the last name; `stage` and `service` are the names of the
current placement (the first by service name, then stage position), else `null`; `done` counts visible tasks
with a current report and `toDo` the rest.


#### `GET /api/parent/children/{childId}/homework?state=todo|done`

`{today, items}` for one of the viewer's approved children (`state` defaults to `todo`; another value → 422
`validation.invalidChoice`). An item is `{id, title, lessonTitle, stage, dueDate, done, completedAt,
completedByYou, completedBy}`: `stage` is the task's stage name, `completedBy` the reporter's display name
(`null` once that account is gone). `todo` is by due date, undated last, then the newest `publishedAt`; `done`
is newest `completedAt` first; the id breaks ties.

#### `GET /api/parent/children/{childId}/homework/{homeworkId}`

`{today, child: {id, name}, task: item + {instructions, resources}, lesson: {title, teachingDate, passage,
memoryVerse}}`.

A child the viewer is not linked to (another family, a revoked link, an unknown id) is always the same 404
`notFound.person`; a task the child may not see (draft, archived, another stage, unknown) is always the same 404
`homework.notAvailable`, so nothing about other families leaks. Malformed ids are 404 too.

#### `PUT /api/parent/children/{childId}/homework/{homeworkId}/completion`

Body `{}`. Reports a visible task done for the child, on the locked task row: 201 when this call created the
report, else 200 with the report that is already there (a double tap, a retry or the other parent's report), both
`{done: true, completedAt, completedBy, completedByYou}`. Only the first report counts; there is never a second
current one. 404 `notFound.person` or `homework.notAvailable` as above; 403 `forbidden` without the parent role.

#### `DELETE /api/parent/children/{childId}/homework/{homeworkId}/completion`

Body `{}` → 204, also when nothing was reported. Either approved parent may undo; the report stays in the history
with `undone_at`/`undone_by`, and a later `PUT` adds a new one. A child who left the stage can still be undone, but
then the task is no longer visible.

### Your own data

Any signed-in account, staff or parent; 401 `auth.unauthenticated` without a session. The `POST` follows the JSON
and Origin rules above (a foreign `Origin` → 403 `forbidden`).

#### `GET /api/me/personal-data`

200 `application/json` as the attachment `khmk-my-data.json`: the account's export, the same as
`app:privacy:export --account=…` (`{kind: account, exportedAt, account, children, homeworkReports}`).
A parent gets the children's names and link status, never their attendance; a copy about a child goes through the
office.

#### `GET /api/me/erasure-request`

`{erasureRequested: {receivedAt, dueOn} | null}`: the account's open erasure request made in the app.

#### `POST /api/me/erasure-request`

Body `{note?}` → 201 `{receivedAt, dueOn}`. It records an erasure request about the account in the office's log
(`channel: app`, `requestedBy` the account's display name); `dueOn` is one month later. 409
`privacy.alreadyRequested` while one is open (a partial unique index also guards against two at once); `note` ≤
2000 → else 422 `validation.tooLong`. Erasing still needs the office's review (`POST /api/data-requests/{id}/erase`).

### Data protection requests

Administrators only (`users: update`, else 403 `privacy.adminOnly`). The office answers each request within one
month (Art. 12(3)).

#### `POST /api/data-requests`

Body `{kind: erasure|copy, subject: {personId?, accountId?}, receipt: {requestedBy, channel: email|letter|in_person,
receivedOn}, note?}` → 201 `{id}`. Exactly one existing member or account (`subject` `privacy.chooseSubject`);
`receipt.requestedBy` `privacy.requestedByRequired`, `validation.tooLong {max: 200}`; `receipt.receivedOn`
`validation.invalidDate`, `privacy.receivedInFuture`; `note` ≤ 2000; another `kind` or `channel` →
`validation.invalidChoice`. `receivedAt` is now when received today, else 12:00 Europe/Berlin on that day;
`dueOn` is one month later, clamped to the month's end (31 Jan → 28 Feb).

#### `GET /api/data-requests`

`{today, open, closed}`: open requests by `dueOn`, closed ones newest first. A row is `{id, kind, status,
subjectName, erasedSubject: {kind, group} | null, requestedBy, channel (`app` when made in the app), receivedAt, dueOn, note, closedAt, closedBy:
AccountRef | null, declineReason, outcome}`; `subjectName` is read from the member or account while it exists, else
`null`.

#### `GET /api/data-requests/{id}`

`{today, request, member: {id, firstName, lastName, displayName, status, memberships} | null, account: {id, name,
email, roles} | null}`; 404 `notFound.request`.

#### `GET /api/data-requests/{id}/erasure`

The review before an erasure: a dry run of `POST …/erase`, rolled back, with the request locked as for the
erasure → 200 `{erase, keep, mentions}`:

- `erase: {person, memberships, attendance, completions, guardianLinks, account, roles}`: rows that go (`person`
  and `account` are 0 or 1).
- `keep: {sessionTotals, accountRows}`: past sessions that keep the member as an anonymous count, and rows the
  account recorded, reported or created that stay without it.
- `mentions: [{id, where: cancelReason|sessionNote|removalReason|lessonNote, text, date, group}]`, oldest first:
  notes that name the member's first or display name as a whole word (any case), in the meetings of the member's
  stages and their services' meetings without a stage, other members' removal reasons there, and the lessons of
  the member's stages. `group` is the meeting or the stage; `date` the session's or the lesson's date.

A request that can't be erased answers with the erasure's own 409 (`privacy.notOpen`, `privacy.notErasure`,
`privacy.subjectGone`, `privacy.ownAccount`, `roles.lastAdministrator`); a malformed or unknown id → 404
`notFound.request`; 403 as above.

#### `POST /api/data-requests/{id}/erase`

Body `{clearNotes: boolean}` → 200 with the request's row (`status: done`, `closedBy` the viewer, `outcome` the
review's counts plus `notes`, `subjectName: null`, `erasedSubject: {kind: member, group}` with the member's last
stage, else its service, or `{kind: account, group: null}`). One transaction with the request locked: `clearNotes:
true` first empties every mention, then the member and the account are erased as by `app:privacy:erase-person`
and `app:privacy:erase-account`; any failure changes nothing. Every request about the erased member or account
(copy requests too, and also after a console erasure) loses the link and keeps only `erasedSubject`. 409
`privacy.notOpen`, `privacy.notErasure`, `privacy.subjectGone`, `privacy.ownAccount`, `roles.lastAdministrator`;
`clearNotes` missing or not a boolean → 422 `validation.invalidType`; 403 and 404 as above.

