# Data model (proposal)

This is the data model the UI prototype is built on. It is a proposal for this API: the tickets that
build each feature settle the details, and the migrations become the source of truth.

In the `ui` repository, the prototype's TypeScript types (`ui-prototype/src/lib/domain/types.ts`) mirror
the API resources described here, and its mock layer (`ui-prototype/src/server/mock/`) enforces the same
rules. The parked branch `spike/mvp-domain-model` in this repository implemented the attendance part with
Doctrine on Symfony 7.4. It has no lessons, homework or parent access.

## Conventions

- **Ids** are UUIDv7, created by the application.
- **Instants** (`*_at`) are `timestamptz` and are sent as RFC 3339 strings with an offset
  (`2026-11-08T12:00:00+01:00`).
- **Local dates** (`occurrence_date`, `teaching_date`, `due_date`, `starts_on`) are `date` values in the
  church's time zone, Europe/Berlin. Times of day (`start_time`) are `time`. A schedule says "Sundays
  12:00" in local time, so daylight saving time never moves a meeting.
- **History is kept.** Memberships are ended, not deleted. Attendance corrections, homework undos and
  revoked parent access set a timestamp instead of removing the row. Archiving hides things without losing
  them. Only drafts that nobody else saw can be deleted. The exception is erasure on a person's request
  (DSGVO Art. 17), which deletes their personal data for good; see [Data protection](#data-protection).
- **JSON names** in the API are camelCase. Columns are snake_case.

## Overview

```mermaid
erDiagram
    SERVICE ||--o{ STAGE : "is divided into"
    PERSON ||--o{ MEMBERSHIP : "takes part through"
    SERVICE ||--o{ MEMBERSHIP : "roster of"
    STAGE |o--o{ MEMBERSHIP : "roster of"
    SERVICE ||--o{ MEETING_SCHEDULE : "meets by"
    STAGE |o--o{ MEETING_SCHEDULE : "meets by"
    MEETING_SCHEDULE |o--o{ MEETING_SESSION : "creates, the day before"
    MEETING_SESSION ||--o{ SESSION_EVENT : "history"
    MEETING_SESSION ||--o{ ATTENDANCE : "present marks"
    PERSON ||--o{ ATTENDANCE : "marked present"
    PERSON |o--o| APP_USER : "may have"
    APP_USER ||--o{ ROLE_ASSIGNMENT : "holds"
    STAGE ||--o{ LESSON : "is taught"
    MEETING_SCHEDULE |o--o{ LESSON : "at schedule and date"
    MEETING_SESSION |o--o{ LESSON : "at one-off session"
    LESSON ||--o{ HOMEWORK_ASSIGNMENT : "gives"
    HOMEWORK_ASSIGNMENT ||--o{ HOMEWORK_COMPLETION : "reported done"
    PERSON ||--o{ HOMEWORK_COMPLETION : "child"
    APP_USER ||--o{ GUARDIAN_RELATIONSHIP : "parent account"
    PERSON ||--o{ GUARDIAN_RELATIONSHIP : "child"
    PERSON |o--o{ DATA_REQUEST : "about"
    APP_USER |o--o{ DATA_REQUEST : "about"
```

Every child, member and visitor is a **person**. Only people who sign in have an **account** (`app_user`):
staff (administrators, service leaders, servants) and parents. Children never have accounts.

## Services, stages and people

| Table | Purpose | Key rules |
| --- | --- | --- |
| `service` | A ministry, e.g. Sunday School or Youth Meeting. | Name unique, ignoring case and extra spaces. Archived, never deleted once it has history. |
| `stage` | A group within a service, e.g. 3rd Class. `position` is the display order used everywhere. | Name unique within the service. Archived like services. |
| `person` | Anyone who takes part. `display_name` is an everyday name ("Kiro" for Kirollos). Optional `birth_date` and postal address (`street`, `postal_code`, `city`). | `status` active or inactive. `birth_date` not in the future. The address is all three parts or none. Staff see birth date and address only within their services. |
| `membership` | Puts a person on the roster of a stage, or of the service itself when `stage_id` is null. | `starts_on`, `ends_on` (inclusive). At most one open membership per person and stage, and per person and service-level roster. Stage and service must match. |

## Meetings and attendance

| Table | Purpose | Key rules |
| --- | --- | --- |
| `meeting_schedule` | A meeting that repeats: weekly (every n weeks on chosen weekdays) or monthly (the nth or last weekday). Start and end time, place, roster mode. | `roster_mode` is `expected` (the stage or service roster is expected) or `open` (no roster; anyone can be marked present). Inactive schedules stop creating sessions. |
| `meeting_session` | One meeting on one date. Created from a schedule, or by hand as a one-off (`schedule_id` null). | Unique `(schedule_id, occurrence_date)`. `occurrence_date` is the schedule date the session stands for and never changes, even when the session is moved, so the generator can't create it twice. `overridden` marks sessions changed by hand; later schedule edits leave them alone. |
| `session_event` | The session's history: opened, closed, reopened, canceled, moved, edited, with who and an optional note. | Append-only. |
| `attendance` | A "present" mark. | At most one current mark per session and person (`removed_at` null). A correction sets `removed_at`, `removed_by` and a reason. There are no absence rows: not marked present is not the same as absent. |

Session status: `scheduled` → `active` (attendance open) → `completed` (attendance closed). A service leader
can reopen a completed session for corrections. A scheduled session can be `canceled` with a reason; a
planned date without a session yet can be canceled or moved ahead of time, which creates its session right
away so the generator skips that date.

### Session generation (backend job, not built yet)

A scheduled console command runs every hour at minute 5. It creates the sessions of active schedules on the
calendar day before they take place, and covers today as well in case a run was missed. It is idempotent:
the unique `(schedule_id, occurrence_date)` key makes a second run a no-op, and a date that already has a
session (moved or canceled included) is never touched again. The prototype simulates this job on its demo
clock; it does not exist in the backend yet.

## Accounts and roles

| Table | Purpose | Key rules |
| --- | --- | --- |
| `app_user` | A sign-in account: email, display name, password hash, language (`en`, `de`, `ar`), optional link to a person, optional `birth_date` and postal address (`street`, `postal_code`, `city`, all three or none; visible to the person and administrators). When the privacy notice was accepted (`privacy_accepted_at`, `privacy_version`). | Email unique, compared in lower case. `approved_at`/`approved_by` stay null until an administrator approves the account; until then it can't sign in. While waiting it keeps what the person wrote when registering (`registered_as` parent or servant, `registration_note`, `registration_service_id`); these are cleared once an administrator decides. Declining deletes the row. Disabled accounts (`disabled_at`) can't sign in and keep their history. |
| `role_assignment` | `administrator` (everything), `service_leader` (one service), `servant` (one service, optionally narrowed to one stage), `parent` (no service). | A stage-scoped role covers only that stage. Parents see only what their approved guardian links allow. |

The backend checks every request against these roles. The frontend only uses them to hide actions a person
can't take.

Account states: registered (waiting) → approved → disabled. Approving a parent creates approved
`guardian_relationship` rows for the children the administrator chose; approving a servant creates their
`role_assignment`. A parent account without any approved child sees an empty portal.

## Lessons, homework and parent access

| Table | Purpose | Key rules |
| --- | --- | --- |
| `lesson` | What is taught at one meeting of a stage: title, summary, passage, memory verse, objectives, teaching points, activities, materials, notes for the servants. A responsible servant and helpers (`lesson_collaborator`). | Linked to its meeting by `(schedule_id, teaching_date)`, the same key as the session, so it can be prepared weeks before the session exists and survives the session being moved. One-off sessions are linked by `session_id`. At most one non-archived lesson per meeting. |
| `homework_assignment` | A small task for the children of the lesson's stage: title, instructions for parents, optional due date, resources. | `stage_id` is the lesson's stage. The due date can't be before the lesson. `published_at` stays set once it was published. |
| `homework_completion` | A parent's report that one child did one task. | At most one current report per task and child (`undone_at` null). Reporting twice keeps the first report. Undo sets `undone_at` and `undone_by`. Only an approved guardian of the child can report, and only while the task is published. |
| `guardian_relationship` | Links a parent account to a child: `parent` or `guardian`. | `pending` → `approved` → `revoked`. Only administrators approve or create links. At most one non-revoked link per account and child. |

Lesson status: `draft` → `ready` → `completed` (taught). Completed is only possible on or after the teaching
date, and each step can be undone. Lessons are archived; a draft lesson can be deleted while none of its
homework was ever published.

Homework status: `draft` → `published` → `archived`. Drafts are never visible to parents. Published homework
goes back to draft only while no parent has reported it done; after that it can only be archived. Archiving
removes it from the parent portal and keeps the reports.

### What parents receive

The parent read models contain only the parent's own approved children, their current stage, published
homework (title, instructions, due date, resources) and the lesson's passage and memory verse. Lesson plans,
notes for the servants, attendance, other children and other families are never part of them. "Done" means
a parent ticked it off; it says nothing about how well the child learned, and nothing is graded or ranked.

### Who a task is for

A task goes to everyone currently on the stage's roster, so a child who joins later sees the stage's
published homework. A child who left keeps the reports already made, shown as no longer in the stage.

## Data protection

| Table | Purpose | Key rules |
| --- | --- | --- |
| `data_request` | A request to see (`copy`) or erase (`erasure`) personal data: who it is about (`person_id`, `app_user_id`), who asked and how (`requested_by`, `channel`: app, email, letter, in person), `received_at`, `due_on` (one month later), a note, and the outcome. | `status` open → done or declined; declining needs a reason. A member's account and an account's member record belong to the same request. Once the data is erased, the request keeps no name: `subject_name` is cleared and `erased_subject` says what it was ("member of 3rd Class"), and the same happens to earlier requests about the same person. `outcome` counts what was deleted per kind. |

### What erasing deletes, and what stays

For a **member** (`person`): the person row, their memberships, attendance marks (corrections included),
homework reports about them and their guardian links. Notes written by others that may name them
(cancel reasons, session notes, correction notes, lesson notes in their groups) are listed for the
administrator, who can have them emptied in the same step. A linked account loses the link.

For an **account** (`app_user`): the account, its roles and guardian links. What the account did for the
church stays, without saying who: attendance it recorded, sessions it closed, history entries, homework
reports it made for a child (they are about the child), lessons and homework it prepared. The columns that
point to the account become null (`recorded_by`, `removed_by`, `closed_by`, `actor_id`, `undone_by`,
`approved_by`, …); `homework_completion.completed_by`, `lesson.responsible_id` and
`homework_assignment.created_by` are nullable for this reason, and the apps show "a former account".

**Past totals stay right.** A session's counts ("12 of 14 present") would shrink when someone is erased.
Three counters on `meeting_session` keep them: `erased_roster`, `erased_present` and
`erased_roster_present`, increased for every completed, active or past session the person was on the roster
of or marked present at. The counts add them; the lists no longer show the person.

The copy (`copy` requests and the download on the account page) is JSON with the member's record
(birth date and address included), memberships, attendance and homework reports, and the account's details (birth date and address included), roles, linked children and the
reports it made. Password hashes and other people's contact details are never part of it.

Retention is an open question for the church (see the architecture's open questions): how long members
who left and disabled accounts are kept before they are erased the same way.
