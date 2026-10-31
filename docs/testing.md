# Testing

`composer test` creates and migrates the test database `khmk_test` (every context's schema), checks with
`app:migrations:check` that every context's database matches its mapping, then runs the
`Unit`, `Functional` and `Architecture` suites. Every test runs in a
transaction that is rolled back afterwards (DAMA), schema changes included, so tests can run in any order.

## Conventions

- Tests mirror `src`: `tests/Unit/<src path>/<Class>Test.php` for tests without the kernel,
  `tests/Functional/<src path>/<Class>Test.php` for tests with it. The name starts with the class it covers.
- Helpers mirror `src` in `tests/Support/<src path>/`; generic ones (`ApiTestCase`) sit at its root. Fixtures
  live in `tests/Fixtures/Context/<Context>/`. Tests of helpers sit under `tests/Functional/Support/`.
- `tests/Architecture` checks the layout of `src` and `tests` and the context wiring.
- API tests extend `App\Tests\Support\ApiTestCase`: `jsonRequest()` sends JSON like the frontend (`{}` when
  an unsafe request has no body), `responseJson()` decodes the answer, `assertJsonPath('fieldErrors.name.code', …)`
  checks one value, `assertProblem(status, code, params)` and `assertFieldError(field, code, params)` check a
  problem body. `tests/Support/Platform/Problem` holds test-only probe controllers under `/api/test/…`.
- Test data comes from plain builders, no Foundry.
- Dates and times come from `MockClock` (`ClockSensitiveTrait`), never from the real clock.
- Races are proven with database constraints and lock tests, not with threads.

## Database rules

- A failed statement aborts the test's transaction (SQLSTATE 25P02). Run each expected violation inside
  `$connection->transactional()`, which uses a savepoint, or give it its own data-provider case.
- After a failed flush the EntityManager is closed: get a fresh one before continuing.
- Lock tests that need committed rows and a second connection use DAMA's `#[SkipDatabaseRollback]` and one
  shared commit-and-clean-up helper, added with the first lock test (#25).
