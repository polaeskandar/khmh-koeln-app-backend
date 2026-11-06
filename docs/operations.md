# Operations

What production needs, so an EU host can be chosen on Tue 3 Nov (#5). Provisioning follows in #74, data
protection procedures in #112.

To start, everything except the database runs on **one server**, next to **one managed PostgreSQL**, both in
the EU and on a private network. More servers come only when load or availability require them.

## Topology

```mermaid
flowchart LR
    browser["Browser, iOS and Android apps"] -->|"HTTPS only"| proxy
    subgraph server["Server (Docker Compose)"]
        proxy["Proxy: FrankenPHP<br/>app.khmk-koeln.com, TLS, HSTS<br/>serves the static web bundle"]
        proxy -->|"/api/*"| api["API container<br/>Symfony"]
        worker["Worker container<br/>messenger:consume"]
        backup["Daily backup<br/>pg_dump, encrypted"] --> volume[("Backup volume")]
    end
    api --> db[("Managed PostgreSQL 18<br/>one schema per context")]
    worker --> db
    backup --> db
```

- **One origin (D2):** `https://app.khmk-koeln.com` serves the static app at `/` and the API at `/api`, both
  from the proxy on the server. The session cookie is `__Host-` prefixed and first-party, so production needs
  no CORS. HTTP redirects to HTTPS.
- **Containers:** the API and the worker run the same image (#27). One-off commands (`app:migrations:migrate`,
  `app:user:password-link`) run in a container from that image. Docker Compose restarts them after a reboot.
- **Database:** managed by the provider, reachable only from the server over a private network.
- **Backups:** the provider's automated database backups are the first copy. Our daily dump on the server is
  the second, in a different place from the database. It moves to EU object storage once a bucket is set up
  (see [Backups](#backups)).

## Requirements

**Must** blocks the launch. **Should** is wanted, with a workaround if missing.

### Server

| Requirement | Level | Needed by |
| --- | --- | --- |
| EU region; provider bound by a signed GDPR Art. 28 data processing agreement | Must | Special-category data (Art. 9), see [Data protection](architecture.md#data-protection-dsgvo) |
| Data and backups stay in the EU region. A provider headquartered outside the EU (for example in the US) needs a DPA with a valid transfer basis (EU-US Data Privacy Framework, standard contractual clauses) and the data protection adviser's written approval, because its home country's authorities may demand access | Must | Art. 9 data of children and parents |
| One VM with Docker Compose, a firewall that opens only 80 and 443, SSH by key only | Must | The whole stack runs on it |
| Exactly one API container, reachable only through the proxy | Must | Sign-in throttling counts per instance (#19); `trusted_proxies` (#41) |
| TLS certificates renewed automatically by the proxy (FrankenPHP/Caddy, Let's Encrypt), HSTS | Must | `__Host-` session cookie (`Secure`), one origin |
| Encrypted disks for the server and the backup volume | Must | DSGVO Art. 32 |
| An operator shell (SSH) whose command output is not logged | Must | `app:user:password-link` prints one-time links (#30) |
| Security updates of the OS applied automatically, reboots scheduled | Must | The server faces the internet |
| Deploy by image tag, with rollback to the previous tag | Should | Release and rollback procedure (#43) |
| Snapshots of the server | Should | Fast rebuild after a failure |

### Web bundle

| Requirement | Level | Needed by |
| --- | --- | --- |
| Per-path response headers and cache rules (immutable assets, no-cache HTML) | Must | frontend#20 |
| Trailing-slash redirects and a real 404 page, no SPA fallback | Must | Static export routing, frontend#20 |
| `.txt` served as `text/plain` | Must | Next.js RSC payloads |

The exact rules are in frontend#20; the proxy on the server applies them.

### Managed PostgreSQL

| Requirement | Level | Needed by |
| --- | --- | --- |
| PostgreSQL 18; 16 or 17 only if CI (#12) and the backup image (#42) use the same major | Must | `compose.yaml`, migrations tested on 18 |
| Same provider or region as the server, connected over a private network | Must | No database port on the internet |
| Encryption at rest | Must | DSGVO Art. 32 |
| Encoding UTF8 with ICU collations (`de-x-icu`) | Must | German name order in member lists |
| The application owns its database without being superuser and may create schemas | Must | `app:migrations:migrate` runs `CREATE SCHEMA` per context |
| No extensions required | Must | Keeps every managed offer eligible |
| Session-mode connections (no transaction pooler between server and database) | Must | `LOCK_DSN` advisory lock of the hourly generator (#53) and PHP sessions in PostgreSQL (#14) |
| At least two connections per PHP worker, plus the worker, the hourly job and the backup | Must | Doctrine plus the session handler per request |
| TLS `verify-full` for Doctrine, the session handler and `LOCK_DSN`, unless the private network is the only route | Must | Data in transit, DSGVO Art. 32 |
| Server logs kept ≤ 14 days with `log_error_verbosity = terse` | Must | Unique violations would log emails (D6) |
| Automated daily backups with point-in-time recovery, kept 30 days | Must | First backup copy |

### Jobs

| Requirement | Level | Needed by |
| --- | --- | --- |
| One-off commands from the image (migrations before each release) | Must | `app:migrations:migrate` (#43) |
| The session generator every hour at minute 5, with an alert on failure; host cron or Symfony Scheduler in the worker, decided in #53 | Must | `app:sessions:generate` (#53) |
| A daily backup at 02:15 Europe/Berlin, with an alert on failure | Must | #42 |
| Secrets in a root-only `.env.local` on the server or the provider's secret store, never in the image | Must | `APP_SECRET`, `DATABASE_URL` (#41) |
| Logs kept 14 days (to be confirmed by the data protection adviser by Tue 3 Nov) | Must | D6, #112 |
| Uptime check on `GET /api/health/ready` from outside the server, with alerts | Must | Launch watch (#117) |

## Backups

The database provider's backups are the first copy. The second is our encrypted daily dump. For now it stays
on the server, under these conditions:

- **Encrypted before writing:** each dump is encrypted with a public key (`age` or `gpg`); the private key is
  not on the server and is held by the two key custodians.
- **Out of the containers' reach:** the dumps live on their own volume, mounted only into the backup job,
  never into the API or the worker.
- **Kept 30 days:** a cleanup step deletes older dumps after each run.
- **Restore tested:** a restore into a scratch database is part of #42 and is repeated monthly.
- **Temporary:** the dumps move to an EU object storage bucket (S3-compatible, lifecycle rule of 30 days)
  before regular use after launch. Until then a lost or compromised server loses or exposes the second copy;
  the data protection adviser has to accept this.

## Shortlisted hosts

State on 31 Oct 2026. "To confirm" means the public documentation did not answer it; ask the provider before
Tue 3 Nov.

| Must-have | Hetzner Cloud + Ubicloud (DE) | Scaleway (FR) | OVHcloud (FR) | DigitalOcean (US) |
| --- | --- | --- | --- | --- |
| Server: EU VM with Docker | Hetzner Cloud VM, Nuremberg or Falkenstein | Instance, Paris or Amsterdam | Public Cloud instance, e.g. Frankfurt or Gravelines | Droplet, Frankfurt (FRA1) or Amsterdam (AMS3) |
| Managed PostgreSQL 18 | Ubicloud on Hetzner, Germany; 18 is the default | Up to 17 listed; 18 to confirm | 18 announced, availability to confirm | Yes, Standard Edition 15–18 |
| Private network between server and database | To confirm (two providers) | Private Network | vRack / private network | VPC |
| Art. 28 DPA, EU headquarters | Hetzner yes; Ubicloud's seat and DPA to confirm | Yes | Yes | DPA yes (Data Privacy Framework, SCC fallback); US headquarters, needs the adviser's approval |
| Encryption at rest (database) | To confirm | To confirm | To confirm | To confirm |
| ICU, non-superuser owner, session-mode connections | To confirm | To confirm | To confirm | To confirm |
| Automated backups and point-in-time recovery | Yes (Ubicloud) | Yes | Yes | Yes; retention to confirm against our 30 days |
| EU object storage for later | Hetzner Object Storage | Object Storage | Object Storage | Spaces (FRA1, AMS3) |
| Number of processors to sign with | Two | One | One | One |

Not shortlisted: platforms without plain VMs (for example Clever Cloud). DigitalOcean stays on the list only if
the data protection adviser accepts a US-headquartered processor for Art. 9 data; its Standard Edition plans
are limited to 4 GiB of RAM and no standby nodes from 30 Nov 2026, which is enough for this app.

Sources: [Ubicloud on Hetzner](https://ubicloud.com/blog/open-and-portable-managed-postgresql-avail-hetzner),
[Ubicloud changelog 2026-08](https://www.ubicloud.com/docs/changelog/2026-08),
[Scaleway Managed PostgreSQL](https://www.scaleway.com/en/managed-postgresql-mysql/),
[Scaleway database engines API](https://www.scaleway.com/en/developers/api/managed-database-postgre-mysql/engines),
[OVHcloud database lifecycle](https://docs.ovhcloud.com/en/guides/web-cloud/databases/db-eos-eol.md),
[OVHcloud Managed PostgreSQL](https://www.ovhcloud.com/de/public-cloud/postgresql/),
[DigitalOcean PostgreSQL versions](https://docs.digitalocean.com/products/databases/postgresql/how-to/create/),
[DigitalOcean DPA (January 2026)](https://www.digitalocean.com/legal/data-processing-agreement-january-2026).

## Configuration

The variables the application reads. `.env` holds the development defaults; production sets real values in the
environment (#41).

| Variable | Example | Secret | Purpose |
| --- | --- | --- | --- |
| `APP_ENV` | `prod` | No | Symfony environment: `dev`, `test` or `prod` |
| `APP_SECRET` | 32 random hex bytes | Yes | Signs CSRF tokens and other Symfony secrets |
| `APP_SHARE_DIR` | `var/share` | No | Directory for data shared across deploys |
| `DEFAULT_URI` | `https://app.khmk-koeln.com` | No | Base URL for links generated outside a request (console) |
| `DATABASE_URL` | `postgresql://app:…@db:5432/khmk?serverVersion=18&charset=utf8` | Yes | PostgreSQL connection |
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default` | No | Queue of the Messenger worker |
| `SESSION_LIFETIME` | `2592000` | No | Seconds a sign-in session lasts (30 days); also the PostgreSQL session garbage-collection age |
| `FRONTEND_URL` | `https://app.khmk-koeln.com` | No | The web app's URL; its origin is the one origin allowed to send unsafe requests |
| `CORS_ALLOW_ORIGIN` | empty (production), `http://localhost:3000` (`.env.dev`) | No | Comma-separated extra origins for local development: they may send unsafe requests and get credentialed CORS. Each entry is an exact `scheme://host[:port]`; `*` or anything else stops the application. Empty in production. |
| `TRUSTED_PROXIES` | `10.0.0.0/8` or `REMOTE_ADDR` | No | Proxies whose `X-Forwarded-For`, `-Proto` and `-Port` are trusted, so sign-in limits count the real client (#41). Empty: forwarded headers are ignored. |
| `MAILER_DSN` | `mailgun+api://KEY:DOMAIN@default?region=eu` | Yes | Where email goes. Production: Mailgun's EU region (with a DPA, Art. 28) through `symfony/mailgun-mailer`. `.env` and tests use `null://null` (nothing is sent); `.env.dev` uses `log://default`, which writes each email (recipient, subject, text) to stderr instead of sending it. `bin/console app:mail:test <email>` checks the setting with a real account. |
| `MAILER_FROM` | `KHMK Köln <no-reply@khmk-koeln.com>` | No | Sender of every email. The sending domain needs Mailgun's SPF and DKIM records. |

Sign-in limits (5 attempts per email and client, 25 per client, per 15 minutes) are kept in the
`cache.rate_limiter` pool, which is the application cache on the local filesystem. That is right for one API
instance. A second instance needs a shared pool (Redis, or the database through `cache.adapter.doctrine_dbal`),
or each instance counts on its own.

Later issues add their variables here.

## Logging

Production writes JSON lines to stderr (`main` through `fingers_crossed`, `deprecation` separately); the host
collects them. In web requests, debug and info records are buffered (50) and written only when an error-level
record arrives. Console processes also write through the `console` handler, unbuffered and in JSON, at the
verbosity they run with (the worker's `-vv` writes info records). Personal data must therefore never reach a
record at all:

- `RedactingProcessor` runs on every channel before any handler, in `prod` and `test` (in `dev` the mail log
  stays readable). It replaces the value of any context or `extra` key that names personal or secret data (`q`,
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

Logs are kept 14 days unless the data protection adviser decides otherwise by Tue 3 Nov. Access logs of the
reverse proxy leave out query strings (#27, #74).

## Decision checklist for Tue 3 Nov

| Decision | Owner |
| --- | --- |
| Provider and region for the server and the database | Project lead with the church |
| Whether a US-headquartered provider (DigitalOcean) is acceptable for Art. 9 data | Data protection adviser |
| DPA signed (Art. 28) with every processor, sub-processors reviewed | Church (controller) |
| DNS control of `khmk-koeln.com`; which other subdomains exist (HSTS `includeSubDomains` affects them) | Church |
| Interim backups on the server accepted; date for moving them to object storage | Data protection adviser and project lead |
| Backup encryption key and its two custodians | Church and project lead |
| Retention: logs 14 days, backups 30 days | Data protection adviser |
| Alert recipients for the hourly job, the backup and the uptime check | Project lead |
| Who gets production access (SSH, database) | Church and project lead |
| Monthly cost approved | Church |
