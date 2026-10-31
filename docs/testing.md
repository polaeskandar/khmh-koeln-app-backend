# Testing

`composer test` creates and migrates the test database `khmk_test`, then runs PHPUnit. Every test runs in a
transaction that is rolled back afterwards (DAMA), schema changes included, so tests can run in any order.

## Conventions

- Tests mirror the modules: `tests/<Module>/…`.
- API tests extend `App\Tests\ApiTestCase`: `jsonRequest()` sends JSON like the frontend (`{}` when an unsafe
  request has no body), `responseJson()` decodes the answer, `assertJsonPath('fieldErrors.name.code', …)`
  checks one value.
- Test data comes from plain builders in `tests/Factory`, no Foundry.
- Dates and times come from `MockClock` (`ClockSensitiveTrait`), never from the real clock.
- Races are proven with database constraints and lock tests, not with threads.

## Database rules

- A failed statement aborts the test's transaction (SQLSTATE 25P02). Run each expected violation inside
  `$connection->transactional()`, which uses a savepoint, or give it its own data-provider case.
- After a failed flush the EntityManager is closed: get a fresh one before continuing.
- Lock tests that need committed rows and a second connection use DAMA's `#[SkipDatabaseRollback]` and one
  shared commit-and-clean-up helper, added with the first lock test (#25).
