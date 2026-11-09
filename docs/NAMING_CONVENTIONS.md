# Naming conventions

How we name classes, methods, properties, variables and tests, and the words we use for the church's concepts.
[Architecture](architecture.md) says where code lives and [Code style](code-style.md) how it is written; this
document is the one place for names. `composer check` enforces the rules marked with a tool, reviews the rest.

## Sources

| Source | Settles | Here |
| --- | --- | --- |
| PSR-1 (PHP-FIG) | Classes in StudlyCaps, methods in camelCase, constants in UPPER_CASE (MUST) | Required; Mago and PHP Insights |
| PSR-4 | The namespace is the folder and the file is the class (MUST) | Required; Composer, `TestLayoutTest` |
| PSR-12 / PER Coding Style | Layout only; inherits PSR-1 | Required; `mago format` |
| PSR-11 | `get()` throws when the entry is missing, `has()` asks | Adopted for lookups (§3) |
| Doctrine ORM | `find()`/`findOneBy()` return `null`, `save`/`remove` on repositories; the naming strategy derives column names from property names | Adopted (§3, §6) |
| Symfony coding standards | camelCase variables and methods, snake_case route names and parameters, UpperCamelCase enum cases, the suffixes `Interface`, `Trait`, `Exception`, the prefix `Abstract`, `As…`/`Map…` attributes | Adopted |
| Symfony conventions | One verb per operation on a class's main relation (`get`, `has`, `all`, `add`, `remove`, `count`, …) | Informs §3 |
| Domain-driven design (Evans, Vernon) | One ubiquitous language per bounded context, intention-revealing names, entities and value objects as domain nouns | Adopted; the glossary (§7) |
| Command–query separation (Meyer) | A method changes state or answers a question, not both | Commands are verbs, queries nouns or questions (§3) |
| *Clean Code*, ch. 2 | One word per concept, no puns, no noise words (`Manager`, `Data`, `Info`), no encodings | Adopted |
| PHPUnit | `*Test` classes, `test*` methods | Adopted (§5) |

The rest is this project's choice: workflows, choices and drafts, application services named for what they
manage, accessors without `get`.

## 1. Principles

- **The domain's words.** Code uses the glossary's term for a concept, the same term in every layer. Two concepts
  never share a word, and one concept never has two.
- **Say what, not how.** A name tells what a class is for or what a method returns or changes. A method that changes
  state says so with its verb; a method named like a question or a noun changes nothing.
- **Context, not repetition.** The namespace and the class carry context, so members don't repeat it:
  `ChurchServices::create()`, not `createService()`; `Stage::name()`, not `stageName()`.
- **No noise.** No `Manager`, `Helper`, `Processor` (except where a library names the role), `Util`, `Data`, `Info`,
  `Common`, `Base`. No abbreviations except `id`, `URL`, `API`, `JSON`, `CSV`, `UUID`, `SQL`.
- **As long as the reader needs.** A local variable in a three-line method can be short; a public method or a class
  read far from its definition is explicit. Length is not a goal either way.
- **External names stay.** A rename never changes a route, a JSON field, a column or another contract (§6).

## 2. Classes

Every class is a noun or a noun phrase. A role suffix is used only where the table asks for one.

| Role | Folder | Named | Example | Checked by |
| --- | --- | --- | --- | --- |
| Controller | `Interface/Api/<Area>/` | `<Resource>Controller`; a controller split for size names its part: `<Resource>WritesController`, `<Resource>LifecycleController` | `StagesController` | guard |
| API request | `Interface/Api/<Area>/Request/` | `<Name>Request` | `StageRequest` | guard |
| API response | `Interface/Api/<Area>/Response/` | `<Model>Response`, built with `::of()` | `StageListResponse` | guard |
| Console command | `Interface/Console/<Area>/` | the command name in StudlyCaps + `Command`: `app:user:role:add` → `UserRoleAddCommand` | `UserCreateCommand` | guard |
| Console request | `Interface/Console/<Area>/Request/` | `<Command>Request` | `UserCreateRequest` | guard |
| Workflow | `Workflow/<Area>/` | `<Verb><Subject>Workflow`: an imperative verb for a write, `Show` for one thing, `List` for many. When the API and the console run the same process, the console's, which finds the account by its email, ends in `ByEmailWorkflow` | `GiveRoleWorkflow`, `GiveRoleByEmailWorkflow` | guard |
| Workflow input | `Workflow/<Area>/Model/` | `<Subject>Choice`: what a person chose or entered; `<Subject>Filter` for the filter of a listing | `RecurrenceChoice`, `AgendaFilter` | test |
| Workflow output | `Workflow/<Area>/Model/` | a noun for what is shown: `…List` (a whole listing), `…Row`/`…Item`/`…Entry` (one element), `…Details`/`…Detail`/`…View` (one thing), `…Summary` (one thing inside another), `…Reference` (an id and a name to link to) | `StageList`, `ScheduleDetails` | review |
| Application service | `Context/<C>/Application/Service/` | what it manages, usually a plural noun; a class split by job names the job: `…Writes` (create, edit), `…Archives`, `…Deletions`, `…Transitions`, `…Lookup` (reads for other contexts), `…Traces` (a context's references for erasure). Never `…Service`, `…Manager`, `…Helper`, `…Processor`, `…Util` | `ChurchServices`, `ScheduleWrites` | test |
| Entity | `Context/<C>/Domain/Entity/` | the glossary's noun, singular; the table may keep another name through `#[ORM\Table]` | `HomeworkTask` | review |
| Value object | `Context/<C>/Domain/ValueObject/` | a noun; an unchecked input the context validates is `<Subject>Draft` | `ServiceName`, `RecurrenceDraft` | guard |
| Enum | `Context/<C>/Domain/Enum/` | a singular noun; backed; cases in UpperCamelCase | `SessionStatus::Completed` | guard, `EnumsAreBackedTest` |
| Repository | `Context/<C>/Domain/Repository/` | `<Entity>RepositoryInterface`, or `<Query>RepositoryInterface` for a read across entities | `SessionRepositoryInterface` | guard |
| Doctrine repository | `Context/<C>/Infrastructure/Persistence/Repository/` | `Doctrine<Entity>Repository` / `Doctrine<Query>Repository` | `DoctrineRosterRepository` | guard |
| Repository filter | `…/Repository/Filter/` | `<Entity>Filters` | `ServiceFilters` | guard |
| Repository writer | `…/Repository/Writer/` | what it writes | `MissingSessions` | review |
| Port | `Context/<C>/Domain/Interface/` | `<Capability>Interface`; its adapter `<Technology><Capability>` | `GeneratorLockInterface`, `SymfonyGeneratorLock` | guard |
| Platform contract | `Platform/<Module>/Contract/` | `<Capability>Interface` | `TransactionsInterface` | guard |
| Exception | `Context/<C>/Domain/Exception/`, `Library/<Package>/Exception/` | `<Rule>Exception` in a context; Library's coded problems are named for the problem | `RuleViolation`, `NotFound` | guard |
| Attribute | `Context/<C>/Domain/Attribute/` | what it declares | | guard |
| Framework integration | `Infrastructure/Security`, `Platform/*/Service` | the role suffix the framework uses: `…Voter`, `…Listener`, `…Provider`, `…Type`, `…Translator`; Monolog's `…Processor` | `GuardianVoter`, `ProblemListener` | review |
| Library | `Library/<Package>/{Client,Model,Exception,Mapping}` | what it wraps; mapped superclasses end in `Entity` | `LocalDate`, `Calendar`, `CreatedAtEntity` | guard |

## 3. Methods

Names are camelCase verbs or verb phrases for commands, nouns or questions for queries.

### Commands

| Verb | Means | Example |
| --- | --- | --- |
| `create` | a new record | `ChurchServices::create()` |
| `add` | a new item inside a parent | `ServiceStages::add()`, `Memberships::add()` |
| `edit` | replaces the editable fields | `ScheduleWrites::edit()` |
| `rename`, `change<Field>` | replaces one field | `PersonRecords::rename()`, `CurrentAccounts::changeLanguage()` |
| `archive`, `restore` | hides a record and brings it back; history stays | `ServiceArchives::archive()` |
| `delete` | the record is gone; only for records nothing depends on (`Delete…Workflow`, `DELETE` routes) | `LessonDeletions::delete()` |
| `remove` | an item leaves a collection or a mark is corrected; the record it belonged to stays | `AttendanceMarks::remove()` |
| `publish`, `unpublish`, `open`, `close`, `reopen`, `cancel`, `activate`, `deactivate`, `approve`, `decline`, `end`, `move`, `reorder` | a state change, named as the domain names it | `SessionTransitions::reopen()` |
| `link`, `unlink`, `revoke` | connects two records; `revoke` ends a guardian link and keeps it | `Guardianship::link()` |
| `give`, `take` | a role | `RoleAssignments::give()` |
| `record` | appends a fact | `AttendanceMarks::record()` |
| `report`, `undo` | a homework report | `HomeworkReportWrites::report()` |
| `send` | an email leaves | `PasswordLinks::sendTo()` |
| `generate` | creates records derived from others | `SessionGenerator::generate()` |
| `erase` | deletes personal data on request (Art. 17) | `PersonErasures::erase()` |
| `forget` | drops a context's references to an erased person or account | `AccountTraces::forget()` |
| `purge` | deletes what expired | `UnconfirmedAccounts::purge()` |

Not used: `update`, `process`, `handle` and `execute` (except where a framework interface names them), `do`,
`manage`, `set…` (entities change through domain verbs). `save` and `remove` are the repositories' Doctrine verbs;
`run` runs a callable (`TransactionsInterface::run()`).

### Queries

- `get…()` returns a record that must exist and throws `NotFound` otherwise, like PSR-11's `get()`:
  `MeetingSessions::get($id)`, `ParentAccounts::getActive($id)`.
- `find…()` returns the record or `null`, like Doctrine's `find()`: `Children::find($personId)`; `findByIds()`
  returns the ones it found.
- A collection is named for what it returns, qualified by how it is chosen, and the class may supply the noun:
  `ServiceStages::activeIn($services)`, `MeetingSessions::on($date)`, `MeetingSessions::openBetween($from, $to)`,
  `AttendanceMarks::ofPerson($personId)`. The rows of a listing screen are `listed()`.
- `count…()` returns an `int`: `Memberships::countInStage()`, `PersonErasures::countGuardianLinksOf()`.
- `chosen()` turns the ids a person chose into records, or fails with a field error:
  `StageChoices::chosen($stageId, $service, 'stageId')`.
- Accessors have no prefix: `Stage::name()`, `Session::status()`. `get` is kept for lookups.

### Predicates

A predicate returns `bool` and changes nothing. It starts with `is`, `has`, `can`, `allows` or `would`, or is a
verb that reads as a question: `includes()`, `occursOn()`, `belongsTo()`, `matches()`.

### Construction and conversion

- `of()` is a static named constructor: the value from raw input, which it checks (`ServiceName::of()`), or a model
  from the records it shows (`StageDetails::of($stage)`). `of<Source>()` names a source that needs saying
  (`LocalDate::ofInstant()`), and other named constructors name the case they build (`none()`, `weekly()`,
  `ahead()`). An instance method is never called `of()`.
- `to…()` converts to another type: `toString()`, `toRecurrence()`.
- Entities have no factories; they are created with `new`.

### Guards, locks and validation

- `ensure…()` throws when the condition does not hold and returns nothing: `ScheduleUse::ensureUnused()`.
- `check()` validates input and throws `InvalidInput`; it may return the checked value: `RecurrenceRules::check()`.
- `require()` refuses access: `AccessCheck::require()`, `MemberCheck::require()`.
- A repository's `with…Lock($id, $work)` runs `$work` holding a row lock (`withWriteLock()`, `withShareLock()`); a
  service's `while…($id, $work)` runs it while the record is held in the named state (`whileKept()`,
  `whileMarkable()`, `whileEditable()`).

### Entry points and callbacks

Workflows, console commands and event listeners have one public method, `__invoke()`. Controller actions are
`list`, `show`, `create`, `edit` and `delete`, or the route's domain verb (`archive`, `publish`, `open`). Methods a
framework interface defines keep its names (`vote()`, `supports()`, `onAuthenticationSuccess()`).

## 4. Properties, parameters and variables

- camelCase (Mago `property-name`, `variable-name`). Constants in SCREAMING_SNAKE_CASE.
- A collaborator is named for what it provides, usually its class without the role suffix: `ChurchServices
  $services`, `HomeworkReportRepositoryInterface $reports`, `AccessCheck $access`.
- A value is named for its domain noun: `$task` holds a `HomeworkTask`, `$link` a `GuardianLink`, `$account` an
  `Account`.
- Singular for one, plural for a collection; a map is `<values>By<Key>` (`$reportsByTask`).
- Ids end in `Id`/`Ids` and hold a `Uuid` or the id string a request sent (`$accountId`, `$sessionIds`); plain `$id`
  when the method's subject is obvious.
- Instants end in `At` (`$completedAt`), calendar days are `…On`, `$date` or `$today` (`LocalDate`), times of day
  `…Time` (`LocalTime`), ranges `$from`/`$to`. An account that acted is `…By` (`$approvedBy`); the account making
  the request is `$actorId` for a write and `$viewerId` for a read.
- Booleans read as a statement about their subject: `$late`, `$created`, `$inStage`, `$isMember`.
- An optional value has a nullable type and the same name it would have otherwise.
- `$data`, `$result`, `$item`, `$value` only when the type gives them meaning (`AccountData $data`,
  `ReportResult $result`, a value object's `$value`).

## 5. Tests

- A test class is `<Class>Test`, or `<Class><Aspect>Test` for one aspect, at the class's path under `tests/Unit` or
  `tests/Functional` (`TestLayoutTest`). Journeys are `<Journey>JourneyTest`.
- A test method is `test` followed by the behaviour as a sentence: the condition and the outcome, in the present
  tense. `testDisablingTheAccountEndsTheSession`, `testAnErasedPersonCannotBeMarkedAnymore`.
- A data provider is a plural noun for its cases (`refusals()`, `violations()`), and each case's key describes it.
- Helpers mirror `src` under `tests/Support`. Builders are plural nouns for what they build (`Accounts`,
  `HomeworkTasks`) with domain verbs (`approved()`, `published()`); scenarios are `<Scenario>Fixture`, test doubles
  `Spy<Thing>`, test-only controllers `<Thing>ProbeController`.

## 6. Names that are contracts

A rename changes PHP names only. These stay as they are, whatever the PHP side is called:

| Contract | Why | How |
| --- | --- | --- |
| Routes and route names | URLs, and the OpenAPI `operationId`s are made from route names | never renamed with code |
| JSON fields | public properties of requests, responses and the models they serialize | keep the property name |
| OpenAPI schema names | the short names of request, response and nested model classes; the frontend generates `Schemas["…"]` from them | rename such a class only to fix a defect, and say so in the commit |
| Tables, columns, indexes | the underscore naming strategy derives a column from its property: renaming a mapped property renames its column | pin the column with `name:`; `app:migrations:check` must stay clean |
| Migrations | their class names are stored in `<context>_migration_versions` | never renamed |
| Stored enum values, problem codes, permission resources | in the database, in the apps' texts (`errors.<code>`) and in `GET /api/me` | never renamed; a case name may differ from its value |
| Console command names and options, environment variables | the runbook and operators' scripts | never renamed |
| `SecurityUser` | serialized into stored sign-in sessions | never renamed or moved |

That is why some PHP names keep words the glossary otherwise avoids: the `users` permission resource and
`app:user:*` commands, the `completion` route segment and the `completedAt` fields of homework reports, the `userId`
of `POST /api/guardian-links`, the `collaborators` of a lesson, the `homework_assignment` table.

## 7. Glossary

One term per concept. Use the term in code, docs and commits; the right column lists what not to call it.

| Term | In code | Meaning | Not |
| --- | --- | --- | --- |
| Service | `Service` (`organization.service`) | A ministry of the church, such as Sunday School or Youth Meeting. Application services that manage them are `ChurchServices`, because every class in an `Application/Service` folder is a service in Symfony's sense | ministry |
| Stage | `Stage` | A group within a service in a fixed order, such as 3rd Class | class, grade |
| Service level | a membership or schedule without `stage_id` | The part of a service outside its stages | |
| Structure | `StructureLookup`, the structure lock | A service with its stages | |
| Group | `MemberGroup`, `PersonGroup` | Where a person is on a roster: a stage, or a service level | |
| Person | `Person` | Anyone who takes part: a child, a youth, an adult, a visitor | user, student |
| Member | `Membership`, the member portal | A person on a roster. A member account is an account linked to its own member record | |
| Member record | an account's `person_id` | The person record an account is linked to | |
| Child | `$childId`, `ParentChildren` | A person seen from a parent account through a guardian link | kid |
| Pupil | `Pupil` (Learning) | A person receiving homework: a child in the parent portal, a member in the member portal | |
| Visitor | `VisitorRow` | A person marked present without being on the session's roster | guest |
| Membership | `Membership` | Puts a person on the roster of a stage or a service level, from one day to another | enrolment |
| Roster | `Roster`, `RosterBook` | Who is in a stage or service level on a day, computed from memberships; `expected` or `open` | class list |
| Placement | `Placement`, `ChosenPlacement` | The service and optional stage a membership, schedule or lesson belongs to | |
| Account | `Account` (`identity.app_user`) | A sign-in account: staff, parents and members with access of their own | user |
| Account request | `AccountRequests` | An account that registered and waits for an administrator's decision | |
| Staff | `StaffAccounts` | Administrators, service leaders and servants | |
| Servant | the Servant role | A volunteer who serves in the services | teacher |
| Parent | the Parent role | An account with approved guardian links to children | |
| Role, permission | `Role`, `Permission` | A named set of permissions; a permission is an action on a resource | |
| Guardian link | `GuardianLink` (`people.guardian_relationship`) | An approved link between a parent account and a child; its `relationship` is `parent` or `guardian`; revoking keeps the row | guardian relationship |
| Parent access | `ParentAccess`, `ParentAccessList` | What approved guardian links give, and the administrators' page that lists and changes them (`GET /api/guardian-links`) | |
| Meeting schedule | `Schedule` (`meetings.meeting_schedule`) | A meeting that repeats: recurrence, times, place, roster mode. A one-off session has a hidden one-off schedule | |
| Recurrence | `Recurrence` | A schedule's weekly or monthly rule | |
| Occurrence | `Occurrences`, `occurrence_date` | A date a schedule's recurrence falls on | |
| Planned date | `PlannedDate` | An occurrence on the agenda, with its session's status, or `planned` before the session exists | |
| Meeting | `MeetingSlot`, `MeetingLink` | A schedule on a date, whether its session exists yet or not; lessons are linked to meetings | |
| Session | `Session` (`meetings.meeting_session`) | One meeting that takes place: created the day before, `scheduled` → `active` → `completed`, or `canceled`. In Identity, "session" is the sign-in session (`SignInController`, `SessionRenewalInterface`) | occurrence |
| Session event | `SessionEvent` | A line of a session's history | |
| Attendance mark | `AttendanceMark` (`meetings.attendance`) | A person marked present at a session; a correction removes it with a reason. There are no absences | attendance record |
| Agenda | `AgendaDates` | The planned dates of a period | |
| Lesson | `Lesson` | What is taught at one meeting of a stage: `draft` → `ready` → `completed` | |
| Helper | `LessonCollaborator`, `LessonHelpers` | A servant who helps the responsible servant with a lesson. The record is a collaborator, because a class ending in `Helper` reads as a utility | |
| Homework, homework task | `HomeworkTask` (`learning.homework_assignment`) | A small task given with a lesson to its stage: `draft` → `published` → `archived`. "Homework" is the resource and the tasks together, "task" one of them | assignment |
| Homework report | `HomeworkReport` (`learning.homework_completion`) | That a pupil did a task, reported by a parent or the member; undoing keeps it with `undone_at` | completion |
| Data request | `DataRequest` | A request to copy or erase personal data | |
| Copy | `ExportMemberDataWorkflow`, `app:privacy:export` | Everything stored about a person or an account, as JSON (Art. 15, 20) | |
| Erasure | `PersonErasures`, `AccountErasures` | Deleting a person's or an account's personal data on request (Art. 17) | deletion |
| Traces | `AccountTraces`, `PersonTraces` | A context's references to an account or a person, which an erasure forgets | |

## 8. Enforcement

- `mago guard`: folders and suffixes of §2 (`mago.toml`).
- `mago lint`: camelCase names (`variable-name`, `method-name`, `property-name`); PHP Insights: PSR-1.
- `tests/Architecture/NamingConventionsTest`: no generic suffixes on application services; no instance method named
  `of()`; an application service's or repository's `get…()` never returns `null` and its `find…()` always may; a
  workflow's input models end in `Choice` or `Filter`; every interface ends in `Interface`.
- `tests/Architecture/TestLayoutTest`: test classes and helpers mirror `src`.
- Reviews: whether a name says what the code does, and whether it is the glossary's word.
