# Operations

What the application needs to run in production, its configuration and the commands operators run. How and where
it is deployed is not part of this repository.

## Runtime

**Must** blocks the launch. **Should** is wanted, with a workaround if missing.

| Requirement | Level | Needed by |
| --- | --- | --- |
| PHP 8.5 with `pdo_pgsql`, `intl` and `opcache`, served by FrankenPHP (as `compose.yaml` does) or any PHP server with `public/` as the document root | Must | `composer.json` |
| Production code only: `APP_ENV=prod`, `APP_DEBUG=0`, Composer without dev packages, `php.ini-production`; PHP hides its version and errors | Must | A debug page would show code and data |
| Everything, data and backups included, in the EU, under a signed GDPR Art. 28 data processing agreement | Must | Special-category data (Art. 9), see [Data protection](data-protection.md#processors-and-data-locations) |
| One origin (D2): `https://app.khmk-koeln.com` serves the static web app at `/` and the API at `/api`, over HTTPS only (HTTP redirects to HTTPS, HSTS) | Must | The `__Host-` session cookie (`Secure`, first-party), so production needs no CORS |
| Exactly one API instance, reachable only through the reverse proxy | Must | Sign-in limits count per instance (§ Configuration); `TRUSTED_PROXIES` |
| Migrations run as a one-off command before new code serves requests, never on start (§ Migrations) | Must | `app:migrations:migrate` |
| The Messenger worker, `php bin/console messenger:consume async`, with the API's code and environment, restarted when it stops | Should | No message is routed to `async` yet; the first one makes it a Must |
| The scheduled commands (§ Scheduled commands), each with an alert on a non-zero exit | Must | Sessions and erasures |
| Secrets passed as environment variables from a secret store, never in the repository or a build | Must | `APP_SECRET`, `DATABASE_URL` (#41) |
| Logs kept 14 days (to be confirmed by the data protection adviser) | Must | D6, #112 |
| An uptime check on `GET /api/health/ready` from outside, with alerts; `GET /api/health` never touches the database | Must | [API contract](api.md#health) |

### Database

| Requirement | Level | Needed by |
| --- | --- | --- |
| PostgreSQL 18, the version CI and `compose.yaml` run the migrations and tests on | Must | Migrations tested on 18 |
| Reachable only from the app over a private network, no database port on the internet | Must | DSGVO Art. 32 |
| Encryption at rest | Must | DSGVO Art. 32 |
| Encoding UTF8 with ICU collations (`de-x-icu`) | Must | German name order in member lists |
| The application owns its database without being superuser and may create schemas | Must | `app:migrations:migrate` runs `CREATE SCHEMA` per context |
| No extensions required | Must | Keeps every managed offer eligible |
| Session-mode connections (no transaction pooler between the app and the database) | Must | `LOCK_DSN` advisory lock of the hourly generator (#53) and PHP sessions in PostgreSQL (#14) |
| At least two connections per PHP worker, plus the Messenger worker and the scheduled commands | Must | Doctrine plus the session handler per request |
| TLS `verify-full` for Doctrine, the session handler and `LOCK_DSN`, unless the private network is the only route | Must | Data in transit, DSGVO Art. 32 |
| Server logs kept ≤ 14 days with `log_error_verbosity = terse` | Must | Unique violations would log emails (D6) |
| Daily encrypted backups in the EU, kept 30 days and restored only at the host, never on a laptop; after a restore the erasure ledger is replayed before the app reopens | Must | [Data protection](data-protection.md#erasure-ledger) |

## Scheduled commands

| Command | When | Needed by |
| --- | --- | --- |
| `php bin/console app:sessions:generate --no-interaction` | Every hour at minute 5, and once after each release; see [Session generator](#session-generator) | #53 |
| `php bin/console app:privacy:erase-due --no-interaction` | Daily (e.g. `45 3 * * *`); see [Scheduled erasures](#scheduled-erasures) | #315 |

### Session generator

`bin/console app:sessions:generate` creates today's and tomorrow's sessions of the active schedules (church-today in
Europe/Berlin) and exits. It runs every hour at minute 5 and once after each release:

```cron
5 * * * * php bin/console app:sessions:generate --no-interaction
```

- A run takes a non-blocking PostgreSQL advisory lock (`session-generator`, Symfony Lock on the Doctrine connection,
  so it needs a session-mode connection). When another run holds it, the command logs that and exits 0.
- Exit codes: 0 done or skipped, 1 a schedule failed (the others are created), 2 an invalid `--date`. Alert on
  anything but 0.
- Each run writes one JSON record to stderr on the `session_generator` channel, outside the error buffer: `today`,
  `created`, `existing`, `failed`, `durationMs`, no personal data.
- A missed day is backfilled with `--date=YYYY-MM-DD` (never after church-today); it creates that day's and the next
  day's sessions.

### Scheduled erasures

An erasure request made in the app waits 14 days (`erasesOn`), so the person can withdraw it. `bin/console
app:privacy:erase-due` erases every request whose `erasesOn` is church-today or earlier and that is still open: the
account and, when it is linked to its own member record, that record too, as `POST /api/data-requests/{id}/erase`
with `clearNotes: true` does, and closes the request with the counts. It runs once a day:

```cron
45 3 * * * php bin/console app:privacy:erase-due --no-interaction
```

- Running it again erases nothing new: a closed, withdrawn or not yet due request is left alone, so a missed day is
  caught up by the next run.
- Each request is erased in its own transaction. A failure (for example `roles.lastAdministrator`) leaves that
  request open, is logged as `A scheduled erasure failed.` with the request id only, and the others still run.
- It prints only counts (`Erased 2 scheduled requests, 0 failed.`). Exit codes: 0 done, 1 a request failed. Alert on
  anything but 0.
- `--dry-run` runs the same steps inside a transaction that is rolled back and prints what it would have erased.

## First administrators

An empty database has no account. After migrating it, create each administrator with
`php bin/console app:user:create <username> <email> "<name>" --role=administrator`, typing the password twice (or piping
it with `--password-stdin`), and hand it over in person.

## Importing members

The office's class lists can be imported once with a console command, all or nothing, under the API's rules:

```sh
php bin/console app:members:import:dry-run members.csv   # prints what it would create, keeps the file
php bin/console app:members:import members.csv           # imports in one transaction, then deletes the file
```

`composer members:import -- members.csv` runs the import too.

- **Columns:** `first_name, last_name, display_name, service, stage` in the header row, in any order;
  `first_name`, `last_name` and `service` are required, and an unknown or misspelled column refuses the whole
  file before anything is read. A blank `stage` is the service's own roster. Services and stages are matched by name, ignoring case and extra spaces. Memberships
  start today.
- **The file:** UTF-8 only ("CSV UTF-8" in Excel), with or without a BOM, separated by `;` or `,`; any other
  encoding is refused with a message. A quoted field may span lines (a spreadsheet cell with a line break);
  errors name the line the row starts on.
- **Errors:** any invalid line (the API's codes, e.g. `last_name: people.lastNameRequired`,
  `stage: meetings.chooseStage`) stops the run with exit 1, lists every bad line, and writes nothing.
- **Re-runs and namesakes:** a row whose person is already on that roster is skipped, so running a file twice
  creates nothing. A row with the name of an existing person is skipped and reported, unless
  `--duplicates=allow` creates a second person. A person listed for several rosters is created once.
- **Personal data:** names appear only in the command's output, never in logs. Copy the file to the server over
  SSH (`scp`/`sftp`) only, never by email or a shared folder, into a directory readable only by the operator;
  a successful import deletes it, and after a failed one delete it yourself once the import is done.

## Configuration

The variables the application reads. `EnvironmentVariablesAreDocumentedTest` fails for one that the
configuration or the code reads and this table lacks; `APP_DEBUG` is read by the kernel itself. `.env` holds
development defaults, and values set in the environment win, so production sets every variable it needs, at least
`APP_SECRET`, `DATABASE_URL`, `DEFAULT_URI`, `TRUSTED_HOSTS` and `TRUSTED_PROXIES` (empty when no
proxy forwards requests). Secrets live only in the host's secret store, never in the repository; the Symfony
secrets vault is not used.

| Variable | Example | Secret | Purpose |
| --- | --- | --- | --- |
| `APP_ENV` | `prod` | No | Symfony environment: `dev`, `test` or `prod` |
| `APP_DEBUG` | `0` | No | Symfony debug mode; `0` in production |
| `APP_SECRET` | 32 random hex bytes | Yes | Signs CSRF tokens and other Symfony secrets |
| `APP_SHARE_DIR` | `var/share` | No | Directory for data shared across deploys |
| `DATABASE_URL` | `postgresql://app:…@db:5432/khmk?serverVersion=18&charset=utf8` | Yes | PostgreSQL connection |
| `LOCK_DSN` | `flock` | No | Store of Symfony's default lock factory (the `symfony/lock` recipe's value). The administration lock (#22) is a PostgreSQL advisory lock on the Doctrine connection and doesn't read it. |
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default` | No | Queue of the Messenger worker |
| `SESSION_LIFETIME` | `2592000` | No | Seconds a sign-in session lasts (30 days); also the PostgreSQL session garbage-collection age |
| `FRONTEND_URL` | `https://app.khmk-koeln.com` | No | The web app's URL; its origin is the one origin allowed to send unsafe requests |
| `CORS_ALLOW_ORIGIN` | empty (production), `http://localhost:3000` (`.env.dev`) | No | Comma-separated extra origins for local development: they may send unsafe requests and get credentialed CORS. Each entry is an exact `scheme://host[:port]`; `*` or anything else stops the application. Empty in production. |
| `MOBILE_APP_ORIGINS` | `capacitor://localhost,https://localhost` | No | Comma-separated origins of the iOS and Android apps: their preflights allow `Authorization` and `Content-Type` without credentials. They are not allowed to send cookie requests. |
| `API_TOKEN_LIFETIME` | `P90D` | No | How long a bearer token from `POST /api/auth/tokens` lasts, as an ISO 8601 duration |
| `TRUSTED_PROXIES` | `10.0.0.0/8` or `REMOTE_ADDR` | No | Only the reverse proxy's address or private range. Its `X-Forwarded-For`, `-Proto` and `-Port` are trusted, so sign-in limits count the real client; `X-Forwarded-Host` is not, so the proxy passes the public `Host` through. Empty: forwarded headers are ignored. |
| `TRUSTED_HOSTS` | see below | No | Production only: a regular expression of the `Host` values the API answers; anything else gets 400. Add the host every health probe sends (`127.0.0.1` for a probe on the same machine). Empty turns the check off, so production must set it. |
| `DEFAULT_URI` | `https://app.khmk-koeln.com` | No | Base URL for links generated outside a request (console) |
| `SENTRY_DSN` | empty, or `https://<key>@<host>/<project>` | Yes | Error tracking for 5xx responses (Sentry's EU region or a self-hosted GlitchTip). Empty, the default: no event is ever built. Set it only once the service's data processing agreement is signed. |
| `TEST_TOKEN` | `1` | No | Test only: the PHPUnit runner's suffix for parallel test databases |

The production `TRUSTED_HOSTS`, with the probe hosts added at the end:

```text
^(app\.khmk-koeln\.com|localhost|127\.0\.0\.1|<probe host>)$
```

Sign-in limits (5 attempts per email and client, 25 per client, per 15 minutes) are kept in the
`cache.rate_limiter` pool, which is the application cache on the local filesystem. That is right for one API
instance. A second instance needs a shared pool (Redis, or the database through `cache.adapter.doctrine_dbal`),
or each instance counts on its own.

The session cookie in production is `__Host-khmk_session`: always `Secure` (not `auto`), `HttpOnly`,
`SameSite=Lax`, `Path=/`, no `Domain`. Every `/api` response, errors included, carries `Cache-Control: no-store`.

## Logging

Production writes JSON lines to stderr (`main` through `fingers_crossed`, `deprecation` separately); the host
collects them. In web requests, debug and info records are buffered (50) and written only when an error-level
record arrives. Console processes also write through the `console` handler, unbuffered and in JSON, at the
verbosity they run with (the worker's `-vv` writes info records). Personal data must therefore never reach a
record at all:

- `RedactingProcessor` runs on every channel before any handler, in `prod` and `test`. It replaces the value of any context or `extra` key that names personal or secret data (`q`,
  and any key containing `email`, `password`, `token`, `cookie`, `name`, `note`, `params`, `secret`,
  `authorization` or `reason`) with `[redacted]`, at any depth; diagnostic keys such as `token_class` and
  `firewall_name` stay. A `command` keeps its name and loses its arguments.
- It strips query strings and fragments from URLs, every PostgreSQL `DETAIL:` line (key values and failing rows)
  and email addresses, in the message, the context, `extra` and logged exceptions, including every `previous`
  one. A logged exception becomes a `RedactedFailure` whose message starts with the original class name and that
  keeps the original code and HTTP status, so `excluded_http_codes` still sees it.
- Expected refusals don't flush the buffer: 400, 401, 403, 404, 405, 409, 415, 422 and 429 are excluded. The
  coded `RuleViolation`, `NotFound` and `InvalidInput` are logged at info; other exceptions with these statuses
  (such as Symfony's 401 for a missing sign-in) are logged at error level but still don't flush. 5xx errors
  stay critical and flush. The test environment mirrors these handler settings.

Every request has an id: a valid `X-Request-Id` from the proxy (at most 64 characters of `A-Z`, `a-z`, `0-9` and
`-`) is kept, anything else is replaced by a generated UUIDv7. Every response returns it as `X-Request-Id` and every
log record of the request carries it as `extra.request_id`, so a user's or a probe's id finds all lines of that
request (`jq 'select(.extra.request_id == "…")'` on the collected JSON). The id carries no account id, email or
name.

With `SENTRY_DSN` set, a 5xx answer also sends one event to the tracker: no personal data (`send_default_pii`
off), no breadcrumbs, no user, no extra; the request is only its method and the route's path (`/api/people/{id}`),
without query string, headers, cookies or body; every exception in the chain keeps its class but its message
becomes `[redacted]`, since a message can name a person or carry a database key; stack frames keep file and line
but no source lines or argument values. 4xx answers send nothing. The bundle comes from the `sentry/sentry-symfony`
contrib recipe (`allow-contrib` is on for it); `config/packages/sentry.yaml` keeps the recipe's values and adds
these privacy settings.

Logs are kept 14 days unless the data protection adviser decides otherwise. The access logs of the web server and
the reverse proxy leave out query strings, cookies and `Authorization`.

## Migrations

Migrations run as a one-off command against production before the new code serves requests:
`php bin/console app:migrations:migrate --no-interaction`, with the same environment as the API. It applies the
pending migrations of every context and does nothing when there are none. A context's latest one is
`SELECT max(version) FROM <context>_migration_versions` (`identity`, `organization`, `people`, `scheduling`,
`learning`, `privacy`).

Migrations are written expand/contract, so the release before the new code keeps running against the migrated
schema:

- **Expand first:** new tables, new nullable columns, new indexes and new values ship with (or before) the code
  that uses them; a migration never breaks the previous release's queries.
- **Contract one release later:** dropping or renaming a column or table, and a new `NOT NULL` on an existing
  column, ship in the release after the one whose code stopped using it.
- **Forward only:** no `down` migrations in production; a fix is a new migration. Going back to the previous
  release keeps the migrations, because they were compatible with it.
- Every pull request with a migration ticks "Migration is compatible with the running release" in the template.
