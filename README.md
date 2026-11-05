# KHMK Köln backend

The Symfony API of the KHMK Köln app (Symfony 8.1, PHP 8.5, PostgreSQL 18).

## Setup

Needs only Docker with Compose; PHP and Composer run in the containers.

```bash
docker compose up -d
```

The API runs on http://127.0.0.1:8000, PostgreSQL on `127.0.0.1:5436`.

```bash
docker compose exec api composer check    # format, lint, static analysis, PHP Insights, tests
docker compose exec api composer test     # tests only; arguments go to PHPUnit: composer test -- --filter KernelTest
docker compose exec api composer format   # fix formatting
docker compose exec api composer ci       # what CI runs: validate, audit, fresh test database, check
docker compose exec api bin/console ...   # Symfony console
docker compose down --volumes && docker compose up -d   # start over with an empty database
```

Local overrides go in `.env.local` (git-ignored), never in `.env`. The variables are listed in
[Operations](docs/operations.md#configuration).

## CI

`.github/workflows/ci.yml` runs `composer ci` on every pull request and on pushes to `main`, against
PostgreSQL 18 in UTC (the local database runs in Europe/Berlin, so time-zone assumptions fail in one of the
two). The job `composer ci` is a required status check on `main`. Dependabot proposes Composer and GitHub
Actions updates weekly; actions are pinned by commit SHA.

## Operator runbook

There is no account screen at launch; operators run these commands on the server
(`docker compose exec api bin/console …` locally). Each prints what it changed, or the refusal code and exit status 1.

| Command | Does |
| --- | --- |
| `app:user:create <email> <name> [--role=<name>] [--language=de]` | Creates an approved account. Type the password twice, pipe it with `--password-stdin`, or use `--no-password` and send a password link. |
| `app:user:role:add <email> <role>` / `app:user:role:remove <email> <role>` | Gives or takes a role by name. The last active account that can manage roles keeps that permission. |
| `app:user:disable <email>` / `app:user:enable <email>` | Disables an account (it can't sign in, and its open sessions end for good) or enables it again. Repeating changes nothing. The last active administrator can't be disabled. |
| `app:user:password-link <email>` | Emails the account a one-time link to set the password, valid for 24 hours, in the account's language. A new link replaces the unused ones. |

The link goes only to the account's own address, never to the console or the logs, and is stored only as a
SHA-256 hash.

## Docs

- [Architecture](docs/architecture.md)
- [Data model](docs/data-model.md)
- [API contract](docs/api.md)
- [Code style](docs/code-style.md)
- [Testing](docs/testing.md)
- [Operations](docs/operations.md)
- [Launch checklist](docs/launch-checklist.md)

Commits follow [Conventional Commits](https://www.conventionalcommits.org/) (`feat: …`, `fix: …`).
Git hooks are installed on `docker compose up`: `commit-msg` checks the message, `pre-commit` runs
`composer check`.

## Guardian links

Parents see a child only through an approved guardian link. At launch operators manage links on the server:

| Command | Does |
| --- | --- |
| `app:guardian:link <email> <personId> [--relationship=guardian]` | Links an approved, enabled account to an active child and gives the account the Parent role. Repeating changes nothing. |
| `app:guardian:revoke <email>` | Lists the account's children with their ids. |
| `app:guardian:revoke <email> <personId>` | Ends the account's access to that child; the link stays as revoked. Repeating changes nothing. |

