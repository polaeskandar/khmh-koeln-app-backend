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
| `app:user:create <username> <email> <firstName> <lastName> [--role=<name>] [--language=de]` | Creates a person record and the account that belongs to it, which signs in at once with its username (rules in the [data model](docs/data-model.md#accounts-and-roles)). Type the password twice or pipe it with `--password-stdin`. |
| `app:privacy:export --person=UUID \| --account=UUID [--output=FILE]` | Writes everything stored about one member or account as JSON (Art. 15/20) to a file only you can read; prints only the file name. Never includes password hashes, tokens, sessions or other people's email addresses. |
| `app:privacy:erase-account <id>` (`:dry-run` to preview) | Erases an account (Art. 17): its roles and guardian links go; what it did for the church stays with the actor set to `null` ("a former account"). Refuses the last active administrator. Prints counts only. |
| `app:privacy:erase-person <id>` (`:dry-run` to preview) | Erases a member (Art. 17) in one transaction: first raises the anonymous counters of every counted past session they were on or marked in, then deletes their attendance (corrections included), homework reports, guardian links, memberships and the person. Prints counts only. |
| `app:user:role:add <email> <role>` / `app:user:role:remove <email> <role>` | Gives or takes a role by name. The last active account that can manage roles keeps that permission. |
| `app:user:disable <email>` / `app:user:enable <email>` | Disables an account (it can't sign in, and its open sessions end for good) or enables it again. Repeating changes nothing. The last active administrator can't be disabled. |

## Deployment

Deployment is not part of this repository. [Operations](docs/operations.md) lists what the app needs to run:
PHP, PostgreSQL, the worker, the scheduled commands and the environment variables.
The hand-off issues describe what the removed setup did:
[#332](https://github.com/polaeskandar/khmh-koeln-app-backend/issues/332) (production image),
[#333](https://github.com/polaeskandar/khmh-koeln-app-backend/issues/333) (release, rollback and smoke test),
[#334](https://github.com/polaeskandar/khmh-koeln-app-backend/issues/334) (database backups) and
[frontend#285](https://github.com/polaeskandar/khmh-koeln-app-frontend/issues/285) (web hosting).

## Docs

- [Architecture](docs/architecture.md)
- [Data model](docs/data-model.md)
- [API contract](docs/api.md)
- [Code style](docs/code-style.md)
- [Testing](docs/testing.md)
- [Operations](docs/operations.md): runtime requirements, configuration, logging, migrations and console commands
- [Data protection](docs/data-protection.md): inventory, measures and request procedures

Commits follow [Conventional Commits](https://www.conventionalcommits.org/) (`feat: …`, `fix: …`).
Git hooks are installed on `docker compose up`: `commit-msg` checks the message, `pre-commit` runs
`composer check`.

## Guardian links

Parents see a child only through an approved guardian link. At launch operators manage links on the server:

| Command | Does |
| --- | --- |
| `app:guardian:link <email> <personId> [--relationship=guardian]` | Links an enabled account to an active child and gives the account the Parent role. Repeating changes nothing. |
| `app:guardian:revoke <email>` | Lists the account's children with their ids. |
| `app:guardian:revoke <email> <personId>` | Ends the account's access to that child; the link stays as revoked. Repeating changes nothing. |

## Member accounts

Every account belongs to exactly one person record, created with it, and the link is permanent. A member account
also records how the member's age was checked (16 or older, or a parent's or guardian's agreement under 16); that
opens the member portal with the person's own groups and homework. Member accounts are created by the church office
(#365).

