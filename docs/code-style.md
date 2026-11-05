# Code style

Rules every change follows. `composer check` enforces the ones marked with a tool; reviews enforce the rest.
When a tool and a rule disagree, change the code, never the tool's verdict: no suppressions, no baselines.

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

## 5. Small, final, immutable classes

Every class is `final` (or `abstract` when designed for inheritance) and `readonly` unless it must hold
changing state. Values are built complete in the constructor, never set afterwards; a change makes a new
value. One class has one job: at most 10 methods (Mago `too-many-methods`) and a total cyclomatic complexity of
5 (PHP Insights). A pure function without dependencies is a `static` method of a `final readonly` class
(`ViolationCode::of()`); anything with dependencies is a service, injected through the constructor.

## 6. Precise types, no `mixed`

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

## 7. Names instead of comments

No comments: rename or extract until the code says it, and write down decisions and the reasons behind them in
`docs/`. What stays are docblocks that only hold tags for what PHP cannot type (`@param list<Role>`,
`@var Collection<int, Permission>`, `@template`) and markers tools read (`@mago-expect`, `yaml-language-server`).
A docblock never starts with a sentence. Classes are nouns, methods say what they return or do (`of()`,
`translate()`, `isCode()`), booleans read as questions (`is`, `has`). Names are camelCase. phpcs
`CodingStandard.Commenting.NoComments`, Mago: `variable-name`, `method-name`, `property-name`.

## 8. Native functions only through Library

Outside `src/Library`, code calls no PHP function directly, neither as a call nor as a first-class callable.
Each native operation is wrapped once in a Library model, and the rest of the application uses the model:

| Need | Model |
| --- | --- |
| Strings (`sprintf`, `strtolower`, `str_starts_with`, `preg_*`, `ctype_digit`) | `Strings\Model\Text`; user input (`trim`, `mb_strlen`, whitespace) `Strings\Model\InputText`; name comparison `Strings\Model\NameKey` |
| Narrowing `mixed` (`is_string`, `is_scalar`) | `Types\Model\Type` |
| Lists and maps (`array_*`, `in_array`, `iterator_to_array`) | Doctrine `ArrayCollection`, plus `Collections\Model\Collections` for what Doctrine lacks (`of`, `firstPerKey`, `rekeyed`, `mapPresent`, `withoutKeys`) |
| Paths (`glob`, `dirname`, `basename`) | `Files\Model\Path` |
| Hidden console input | `Console\Client\HiddenPrompt` |
| Dates and times (`date`, `strtotime`, `checkdate`, `mktime`) | `Time\Model\LocalDate`, `Time\Model\LocalTime`, `Time\Client\Calendar` (Carbon underneath) |

A missing operation becomes a method on the fitting model, or a new package `src/Library/<Package>/Model/` when
none fits, with a unit test. Language constructs (`isset`, `instanceof`, casts) are not functions. Tests and the
global entry points (`bin/console`, `public/index.php`, `config/`) may call functions. `mago guard`.

## 9. One way to do each thing

- Errors are exceptions carrying a code (`RuleViolation`, `NotFound`, `InvalidInput`, see
  [Architecture](architecture.md#code-layout)); messages are codes, never sentences.
- Today and instants come from `Time\Client\Calendar` (on Symfony's clock, `MockClock` in tests), never from
  `new DateTimeImmutable()`, `Carbon::now()` or the database clock (`CURRENT_DATE`, `now()`, `::date`;
  `tests/Architecture/SqlReadsNoDatabaseClockTest`). Calendar days are `LocalDate`, times of day `LocalTime`.
- `created_at` and `updated_at` are set by Doctrine lifecycle callbacks of `CreatedAtEntity` and
  `TimestampedEntity`, never by hand (see [Architecture](architecture.md#code-layout)).
- Request data enters through `#[MapRequestPayload]` DTOs with nullable properties and code messages.
- Library packages don't use each other; contexts don't use each other. `mago guard`.

## 10. Tests

Tests mirror `src` (one test class per class, `tests/Architecture/TestLayoutTest`), exercise behaviour the way a
caller does (an HTTP request for an endpoint), and follow the same rules, except that a loop may assert.
