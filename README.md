# KHMK Köln backend

The Symfony API of the KHMK Köln app: Symfony 8.1 on PHP 8.4.1 or newer, with Doctrine ORM 3 and Doctrine
Migrations on PostgreSQL 18. It serves the web app and the iOS and Android apps from the `frontend`
repository. The project is initialized only; features are added through tickets.

## Requirements

| Tool | Version | Notes |
| --- | --- | --- |
| PHP | 8.4.1 or newer (8.5 works too) | Extensions `ctype`, `iconv` and `pdo_pgsql`; `intl` and `opcache` recommended. macOS: `brew install php` |
| Composer | 2 | macOS: `brew install composer` |
| Docker | with Compose | Runs PostgreSQL 18 from `compose.yaml`. A local PostgreSQL 18 works as well. |
| Symfony CLI | optional | For `symfony serve` (`brew install symfony-cli/tap/symfony-cli`) |

## Getting started

```bash
docker compose up -d database   # PostgreSQL 18 on 127.0.0.1:5432, database and user "khmk"
composer install
composer test                   # PHPUnit
composer lint                   # container, YAML and Doctrine mapping checks
symfony serve                   # or: php -S 127.0.0.1:8000 -t public
```

- **Configuration.** Symfony reads `.env` (committed defaults), then `.env.$APP_ENV` (committed; `.env.dev`
  holds a generated `APP_SECRET` for local development only) and finally `.env.local` (git-ignored, your
  machine). Production values will come from real environment variables or Symfony's secrets vault, never
  from committed files. The database password in `compose.yaml` and `.env` is for the local container only.
- `composer.json` sets the platform to PHP 8.4.1, so the lock file stays installable on the minimum PHP
  version even when you develop on PHP 8.5.
- The first boot in the dev environment (for example `composer install`) generates `config/reference.php`,
  the configuration reference IDEs use for autocompletion. It is not ignored; commit it.
- Symfony 8.1 receives bug fixes until the end of January 2027; 8.2 is due in November 2026.

## Docs

- [Architecture](docs/architecture.md): building blocks, decisions and open questions for the whole app.
- [Data model](docs/data-model.md): the proposed tables, rules and status lifecycles.
- Branch `spike/mvp-domain-model`: a parked Symfony 7.4 spike of the attendance domain (entities,
  migration, session generator, 51 tests). Not merged; parts can be reused when the tickets come.

Commits follow [Conventional Commits](https://www.conventionalcommits.org/) (`feat: …`, `fix: …`, `docs: …`).

## Related repositories

- `frontend`: the Next.js web app and its iOS and Android apps (Capacitor).
- `ui`: the clickable prototype and its screenshots, the reference for screens and behaviour.
