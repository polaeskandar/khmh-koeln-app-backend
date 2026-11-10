# Code style

Rules every change follows. `composer check` enforces the ones marked with a tool; reviews enforce the rest.
When a tool and a rule disagree, change the code, never the tool's verdict: no suppressions, no baselines. When a
rule itself makes the code worse, change the rule here and in the tool's configuration, and write down why.

## 1. Transform collections, don't accumulate them

Collections passed between classes are Doctrine collections: `ArrayCollection` where the owner changes it,
`ReadableCollection` in signatures where callers only read it. Derive one collection from another with
`map`, `filter`, `reduce`, `findFirst`, `exists`, `forAll` and `partition`, not with a loop that fills a
result.

```php
// Yes
return new ArrayCollection($directories)->map(self::fromDirectory(...));

// No
$sets = [];
foreach ($directories as $directory) {
    $sets[] = self::fromDirectory($directory);
}

return $sets;
```

A loop is right when its body has an effect instead of a result (setting headers, running a migration per
context, asserting in a test), when it stops at the first match, or when it `yield`s. Arrays remain where PHP or
a framework requires them, for JSON bodies and their `params`, and for literal arguments written inline
(`new RuleViolation('code', ['name' => $name])`). Mago: `no-array-accumulation-in-loop`.

## 2. Pass functions as values

Use first-class callables instead of closures that only forward their arguments: `self::fromDirectory(...)`,
`is_string(...)`, `$this->translate(...)`. A closure that does more is `static` unless it needs `$this`, and an
arrow function when it is one expression. Mago: `prefer-first-class-callable`, `prefer-static-closure`,
`prefer-arrow-function`.

## 3. Pipe a value through steps

When one value goes through two or more single-argument steps, write them in reading order with `|>`, instead
of nesting the calls or naming a variable that is used once.

```php
// Yes
$name = $header |> strtoupper(...) |> self::underscored(...);

// No
$name = self::underscored(strtoupper($header));
```

Method chains on one object (`Text::of($path)->replaceMatches(...)->toString()`) stay chains. Don't wrap a call
in a closure only to make it pipeable; name the step as a method instead.

## 4. Return early, branch little

Guard clauses first, the main path last, no `else` after a `return`, `throw` or `continue`. No nested or
short ternaries, no boolean flag parameters: a flag that changes behaviour becomes two methods or two classes.
Mago: `no-else-clause`, `no-redundant-else`, `prefer-early-return`, `no-nested-ternary`,
`no-shorthand-ternary`, `no-boolean-flag-parameter`, `excessive-nesting`.

## 4a. Blank lines set blocks apart

One blank line separates a block from the statements around it, so a method reads as a sequence of steps:

- a control structure (`if`, `foreach`, `for`, `while`, `do`, `switch`, `try`) has a blank line before and after it;
- `return`, `throw`, `break`, `continue` and `yield` have a blank line before them;
- a statement that spans several lines has a blank line before and after it;

except where the statement opens or closes its block. Consecutive one-line statements stay together, and two
blank lines never follow each other.

```php
$email = Email::of($command->email);
$displayName = DisplayName::of($command->displayName);

if ($this->users->findByEmail($email->value) !== null) {
    throw InvalidInput::field('email', 'accounts.emailTaken');
}

$user = User::approved(
    new Profile($email, $displayName, $command->language),
    passwordHash: $this->passwordHash($command->password),
    approvedAt: $this->calendar->now(),
);

$this->users->save($user);

return $user->id();
```

`composer format` adds missing blank lines (`phpcbf`) and `composer check` fails on them (`phpcs`), in `src`,
`tests` and `tools`, with the ruleset `tools/CodingStandard/ruleset.xml`: Slevomat's
`BlockControlStructureSpacingSniff` and `JumpStatementsSpacingSniff`, and the repo's own
`MultiLineStatementSpacingSniff`. Dev-only tooling like this sniff
lives in `tools/` (`App\Tools`), its tests in `tests/Unit/Tools/`.

## 5. Classes: one responsibility, final and immutable

Every class is `final` (or `abstract` when designed for inheritance) and `readonly` unless it must hold
changing state. Values are built complete in the constructor, never set afterwards; a change makes a new
value. A pure function without dependencies is a `static` method of a `final readonly` class
(`ViolationCode::of()`); anything with dependencies is a service, injected through the constructor.

A class has one reason to change, not one public method. What changes together stays together: one service
applies every transition of a subject, one service creates and edits it. Split a class when it does two jobs, never
to lower a metric. A helper used in one place is a private method unless it is

- a domain concept with a name of its own (a value object, a rule in `Domain/Validation`),
- a boundary to infrastructure (a port, a repository, a query interface, a platform contract),
- something with its own state or lifecycle,
- an algorithm that needs its own tests (`CalendarMath`, the recurrence rules), or
- a contract that more than one class implements.

No pure forwarders: a service whose methods only call a repository goes away; the workflow reads through the
context's query interface and writes through the service that owns the write (see
[Architecture](architecture.md#code-layout)). An interface marks a boundary (port, repository, query, platform
contract) or has a second implementation; no facades, factories, managers or interfaces without one.

The caps below point at a class that does too much. A class that reaches one is split by responsibility; a cap is
never raised for one class or suppressed.

| Cap | Limit | Tool |
| --- | --- | --- |
| Constructor parameters | 7 | Mago `excessive-parameter-list` (`constructor-threshold`) |
| Parameters of any other function | 5 | Mago `excessive-parameter-list` |
| Methods per class | 15 | Mago `too-many-methods` |
| Cyclomatic complexity of a class, summed over its methods | 10 | PHP Insights `CyclomaticComplexityIsHigh` |
| Cyclomatic complexity of a method | 5 | PHP Insights `MethodCyclomaticComplexityIsHigh` |

The caps were 5 constructor parameters, 10 methods and a class complexity of 5. Those numbers split cohesive
services: 63 of 266 application services and workflows sat at exactly five collaborators, transitions of one
lifecycle were spread over several classes, and single-use rule classes and forwarders existed only to stay under
the limits. The higher caps leave room for one cohesive class and still flag one that does several jobs.

## 6. Precise types, no `mixed`, no primitive obsession

`declare(strict_types=1)` everywhere. Parameters, returns and properties have native types; docblocks only add
what PHP cannot express (generics, array shapes, `list`, `positive-int`). `mixed` appears only where a library
hands it over, and is narrowed at once (`Type::of($value)->toStringOrNull()`, `is_string()`), never assigned
or passed on. Arguments are named when the call doesn't explain itself: a literal (`dirname($path, levels: 2)`), several
values of the same type (`new MigrationSet(context: …, schema: …, namespace: …, directory: …)`), an array or
collection whose role isn't obvious (`new Readiness(status: …, checks: […])`), a callback
(`Collections::firstPerKey($items, keyOf: …)`), or an optional argument after skipped ones. Calls whose
meaning is clear from the method name stay positional (`new Problem($status, $code)`, `Text::format($pattern, …)`). Mago analyzer at the strictest level,
`literal-named-argument`, PHP Insights `DisallowMixedTypeHint`.

Enums are backed (`enum Language: string`), so every case has a stable value for the database, JSON and
logs. `tests/Architecture/EnumsAreBackedTest` fails on a pure enum in `src`.

A parameter says what it carries. Interface requests hold the JSON's strings; the workflow turns them into ids
(`Uuid`) and enums once, and the contexts take those: never a raw string for an id, status, language, kind, mode,
recurrence or action. Free text stays `string`. A value object exists when a value has rules or a meaning its type
can't carry (`Email`, `LocalDate`, `Period`), not for every scalar. No boolean flags (Mago
`no-boolean-flag-parameter`), no callback that stands in for one, and no sentinel values (`''` for "none"): a
choice between behaviours is two methods or an enum.

## 7. Names

No comments: rename or extract until the code says it, and write down decisions and the reasons behind them in
`docs/`. What stays are docblocks that only hold tags for what PHP cannot type (`@param list<Role>`,
`@var Collection<int, Permission>`, `@template`) and markers tools read (`@mago-expect`, `yaml-language-server`).
A docblock never starts with a sentence. Names are camelCase, classes are nouns, booleans read as questions. phpcs
`CodingStandard.Commenting.NoComments`, Mago: `variable-name`, `method-name`, `property-name`.

**Vocabulary.** One term per thing, in code, API and docs:

| Term | Means | In code |
| --- | --- | --- |
| Service | What servants run: Sunday School, Youth Meeting | `Service` (Organization) |
| Stage | A class or year group inside a service | `Stage` |
| Group | A service's own roster or one of its stages, the unit of access | `GroupScope` |
| Meeting schedule | When a service or stage meets, as a recurring plan | `Schedule` in Meetings, `MeetingSchedule…` elsewhere |
| Meeting session | One meeting on one date, with its progress | `Session` in Meetings, `MeetingSession…` elsewhere |
| Attendance | The presence marks of a session | `Attendance` |
| Lesson | What a stage is taught at one meeting | `Lesson` |
| Homework assignment | A task given with a lesson | `HomeworkAssignment`, `$task` |
| Homework completion | A person's report that a task is done | `HomeworkCompletion`, "report" in the API |
| Person | Someone on a roster, a member record | `Person` |
| Guardian | A person's parent or guardian, linked to an account | `GuardianRelationship`; "parent" is the role and the portal |
| Account | A sign-in | `User` (table `app_user`), "account" in names and the API |
| Data request | A copy or erasure request under the DSGVO | `DataRequest` |

**Classes by role.** One name per role; a class that plays a role carries its name:

| Role | Name |
| --- | --- |
| A process run by an entry point | `<Verb><Subject>Workflow` (`Workflow/<Area>/`) |
| Applies the transitions of a subject's state | `<Subject>Lifecycle` |
| Creates and edits a subject | `<Subject>Writes` |
| Deletes a subject and what depends on it | `<Subject>Removals` |
| Answers reads that need logic (scope, paging, folding) | `<Subject>Lookup` |
| Resolves ids from a request to a context's entities | `<Subject>Choices`, method `chosen()` |
| Forgets an erased person or account in one context | `PersonTraces`, `AccountTraces` |
| A domain rule across values or entities | `<Rule>Rule` in `Domain/Validation/` |
| A read-only port a workflow may use | `<Subject>QueryInterface` in `Domain/Query/` |
| A Symfony state machine | `<subject>_lifecycle` |

Application services never end in `Service`, `Manager`, `Helper` or `Processor` (`WorkflowLayoutTest`); where
each layer's classes live and how they are shaped is in [Architecture](architecture.md#code-layout).

**Methods by kind.** A method name starts with a verb of its kind:

| Kind | Verbs | Returns |
| --- | --- | --- |
| Command | `create`, `record`, `assign`, `schedule`, `publish`, `archive`, `restore`, `complete`, `cancel`, `open`, `close`, `approve`, `link`, `remove`, `change<Property>` | the changed subject or nothing |
| Query | `find…` (null when missing), `get…` (throws `NotFound`), `list…`, `count…`, `exists…` | a value, never a change |
| Predicate | `is…`, `has…`, `can…`, `should…` | `bool` |
| Guard | `ensure…` (throws the rule's code), `require…` (access) | nothing |
| Transformation | `normalize`, `convert`, `calculate`, `format`, `build`, `to<Type>` | a new value |
| Factory | `of()` from raw values, `from<Source>()` from another model, a state for a starting value (`draft()`) | a new value object |
| Locked call | repositories `with<Kind>Lock()`, services `while<Condition>()` | what the callback returns |

Accessors of entities and value objects are named after what they return (`status()`, `id()`). `__invoke()`,
methods an interface requires and established repository operations (`save`, `remove`) keep their names. No vague
verbs: not `change()` without what changes, not `handle`, `process`, `manage` or `do`, and `of()` only on value
objects.

## 8. Libraries and native functions

Application code reads as camelCase calls on objects. Outside `src/Library` it calls no function, neither
PHP's (`preg_match`, `array_map`) nor a library's (`Symfony\Component\String\u()` and `b()`), neither as a call
nor as a first-class callable (`mago guard`). Language constructs (`isset`, `instanceof`, casts) are not functions.
Tests and the global entry points (`bin/console`, `public/index.php`, `config/`) may call functions.

- A mature library with a class API is used directly, never wrapped in a model that only forwards to it: Doctrine
  collections, Symfony's `Uuid`, `Filesystem\Path`, `Finder`, Validator and Serializer, Carbon through `Calendar`.
- A native function without such a class is wrapped once, in a Library model with a unit test:

| Need | Model |
| --- | --- |
| Formatting, patterns, bytes (`vsprintf`, `preg_*`, `str_starts_with`, `ctype_digit`, `explode`) | `Strings\Model\Text` |
| Narrowing `mixed` (`is_string`, `is_scalar`) | `Types\Model\Type` |
| Lists and maps (`array_*`, `in_array`, `iterator_to_array`) | Doctrine `ArrayCollection`, plus `Collections\Model\Collections` for what Doctrine lacks (`of`, `firstPerKey`, `rekeyed`, `mapPresent`, `withoutKeys`, `mapArray`, `mapItems`) |
| Hashes and checksums (`hash`, `crc32`) | `Checksum\Model\Checksum` |
| Hidden console input | `Console\Client\HiddenPrompt` |
| Dates and times (`date`, `strtotime`, `checkdate`, `mktime`) | `Time\Model\LocalDate`, `Time\Model\LocalTime`, `Time\Client\Calendar` (Carbon underneath) |

- Inside Library, a model delegates to a library where the result is the same, and keeps the native function
  where the library behaves differently; a unit test pins the behaviour either way.
- A model with a domain meaning stays even where a library has a similar method, because the meaning is the
  point: `InputText`, `NameKey`, `SearchWords`, `WholeWord`, `LocalDate`, `Problem`.

Text follows these rules:

| Text | Use | Why not symfony/string |
| --- | --- | --- |
| Whatever a person typed (names, notes, titles, search) | `InputText`: composed to NFC, trims and collapses Unicode space (`\s`: NBSP, U+2003, U+3000, not the BOM or ZWNJ), folds for search (drops accents and harakat, keeps ß and ZWNJ), counts code points | `UnicodeString::trim()` strips the BOM and keeps U+3000, `folded()` maps ß to ss and drops ZWNJ, `length()` counts graphemes |
| A stored uniqueness key of a name | `NameKey` | changing it changes stored keys |
| Ids, codes, paths, headers, patterns | `Text` (on `ByteString` where equal) | `u()`/`b()` are functions |
| A length limit | `InputText::length()`, code points like Validator's `Length` | graphemes count fewer |

New operations go on the fitting model, or into a new package `src/Library/<Package>/Model/` when none fits.
Packages never use each other (`mago guard`).

## 9. One way to do each thing

- Errors are exceptions carrying a code (`RuleViolation`, `NotFound`, `InvalidInput`, see
  [Architecture](architecture.md#code-layout)); messages are codes, never sentences.
- Today and instants come from `Time\Client\Calendar` (on Symfony's clock, `MockClock` in tests), never from
  `new DateTimeImmutable()`, `Carbon::now()` or the database clock (`CURRENT_DATE`, `now()`, `::date`;
  `tests/Architecture/SqlReadsNoDatabaseClockTest`). Calendar days are `LocalDate`, times of day `LocalTime`.
- `created_at` and `updated_at` are set by Doctrine lifecycle callbacks of `CreatedAtEntity` and
  `TimestampedEntity`, never by hand (see [Architecture](architecture.md#code-layout)).
- Request data enters through `#[MapRequestPayload]` DTOs with nullable properties and code messages.
- Console commands are invokable: `#[AsCommand]` on a final class whose `__invoke()` takes its input as
  `#[Argument]` and `#[Option]` parameters. When that would make more than five parameters, the options move
  into one `#[MapInput]` request (`Console/<Area>/Request/*Request`, public properties with defaults). No
  `configure()`, no `$input->getArgument()` or `getOption()`. Console requests are the only classes with public
  writable properties, because Symfony fills them from the outside; PHP Insights' `ForbiddenPublicProperty`
  skips `src/Interface/Console` for that reason alone.
- Library packages don't use each other; contexts don't use each other. `mago guard`.

## 10. Rules and validation

Each rule has one authoritative place, in the layer that owns it:

| Layer | Checks | Where | Error |
| --- | --- | --- | --- |
| Input | Shape: presence, type, format, a choice from a closed set | `#[Assert]` on the Interface request, codes as messages | 422 field errors |
| Domain | Meaning: a value's limits and form, values weighed against each other, invariants of a subject, relations between service, stage, schedule, session, attendance, lesson and homework | the value object for its own value (`ServiceName::of()`); `Domain/Validation/<Rule>Rule` for a rule across values or entities | `InvalidInput` (422, with the request's field path) or `RuleViolation` (409) |
| Transition | Whether a state change may happen now | the state machine's guard ([§ 11](#11-state-transitions)) | `RuleViolation` with the transition's code |

- The domain is the authority. A request may declare the same limit so the API document shows it (`maxLength`),
  never a different one, and the domain checks it again for every caller: console, import, workflow.
- A rule in `Domain/Validation` is a final readonly class with `ensure…()` methods that throw the coded exception.
  It is pure: the application service loads the facts and passes them in; no repository, no Symfony service.
- A one-line check that only one place needs stays where it is (in the value object or the service).
- Codes and their order are the API (`docs/api.md`); moving a rule keeps both.

## 11. State transitions

- A subject with a stored status and transitions whose rules differ per state is a Symfony Workflow
  `state_machine` (one place at a time) named `<subject>_lifecycle`, never `*_workflow`: `App\Workflow` is the
  process layer. Its places are the stored enum values, its transitions are verbs (`open`, `close`, `reopen`,
  `cancel`), and its definition in `config/packages/workflow.yaml` is the only list of them.
- One mechanism: no entity or value object implements the same graph next to the machine.
- A guard decides whether a transition may happen now and blocks with the API's code (`meetings.opensOnTheDay`).
  The `<Subject>Lifecycle` service applies transitions, throws a blocked one as `RuleViolation` in the order
  `docs/api.md` documents, and keeps the subject's write lock and transaction around the change and its effects.
- Effects of a transition (timestamps, the audit row) happen in that service or in a listener of the transition
  (`#[AsTransitionListener]`, `#[AsCompletedListener]`); no chain of listeners for a trivial step.
- A transition into the place the subject is already in is a no-op where the API says so (no timestamp, no audit
  row); otherwise the machine refuses it.
- A status any state may move to freely, a two-state flag (active, archived) or a state derived from dates (an
  account's) needs no machine.
- Tests cover every state against every transition: the result or the code, the timestamps, the audit rows and
  the no-ops.

## 12. Attributes

Framework metadata is an attribute on the code it describes: `#[Route]`, `#[MapRequestPayload]`,
`#[MapQueryString]`, `#[MapQueryParameter]`, `#[Assert\…]`, `#[AsCommand]`, `#[AsEventListener]`,
`#[AsMessageHandler]`, `#[AsGuardListener]`, `#[AsTransitionListener]`, `#[AsCompletedListener]`,
`#[AsEnteredListener]`, `#[AsAlias]`, `#[Autowire]`, `#[Target]`. Configuration that is data rather than code
(state machine places and transitions, firewalls, rate limits, Messenger routing) stays in `config/packages`.
Only official attributes; a domain attribute (`Domain/Attribute`) exists only when code reads it. Access is checked
by the workflow through `AccessCheck`, not with `#[IsGranted]` (see [Architecture](architecture.md#who-may-do-what)).
An attribute never replaces PHP that reads better, such as a long expression.

## 13. Tests

Tests mirror `src` (one test class per class, `tests/Architecture/TestLayoutTest`), exercise behaviour the way a
caller does (an HTTP request for an endpoint), and follow the same rules, except that a loop may assert.
