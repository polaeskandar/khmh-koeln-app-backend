# KHMK Köln backend

The Symfony API of the KHMK Köln app (Symfony 8.1, PHP 8.5, PostgreSQL 18).

## Setup

Needs only Docker with Compose; PHP and Composer run in the containers.

```bash
docker compose up -d
```

The API runs on http://127.0.0.1:8000, PostgreSQL on `127.0.0.1:5436`.

```bash
docker compose exec api composer test     # tests
docker compose exec api composer check    # format, lint, static analysis, PHP Insights
docker compose exec api composer format   # fix formatting
docker compose exec api bin/console ...   # Symfony console
```

Local overrides go in `.env.local` (git-ignored), never in `.env`.

## Docs

- [Architecture](docs/architecture.md)
- [Data model](docs/data-model.md)
- [API contract](docs/api.md)

Commits follow [Conventional Commits](https://www.conventionalcommits.org/) (`feat: …`, `fix: …`).
Git hooks are installed on `docker compose up`: `commit-msg` checks the message, `pre-commit` runs
`composer check`.
