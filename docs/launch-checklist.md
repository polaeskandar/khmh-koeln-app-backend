# Launch acceptance checklist

Run on production by #115 before the release rehearsal (#116). Agreed with the project lead on Wed 4 Nov,
before anyone tests. Full German and Arabic screen QA is frontend#89.

## Rules

- **Only fictional data.** Names come from the table below; emails use `example.org`, which can never
  receive mail. No real person, child or address is entered.
- **No fixtures.** Each journey uses only data from earlier journeys or the first administrator.
- **Every step has an expected result.** A step passes only when the result appears exactly as written. Any
  difference is a bug: file it, link it in the result table, and carry on if the journey can continue.
- **Afterwards** the testers delete every account and person they created (data protection request "erasure",
  #113), so production starts empty.

## Before the first run

| Step | Expected result |
| --- | --- |
| On the server, create the first administrator: `bin/console app:user:create qa-admin@example.org "QA Admin" --role=Administrator` and type a password twice | The command prints that the approved account exists with the administrator role; the password is not echoed |
| Note the date, the weekday and the time | Needed for journey 4: the schedule uses today's weekday |

## Runs and the matrix

The 22 cells are grouped into four runs. A run goes through its journeys in order on one device and in one
language, and tags everything it creates with its letter, e.g. `QA-A Sunday School`, `qa-a-parent@example.org`.

| Run | Device | Language | Journeys | Uses data from |
| --- | --- | --- | --- | --- |
| A | Phone, 390 px wide | English | 1, 2, 3, 4, 5, 6, 7 | First administrator |
| B | Desktop | English | 1, 2, 3, 4, 5, 6, 7 | First administrator |
| C | Phone, 390 px wide | Arabic (right to left) | 1, 3, 5, 7 | Run A's service, `QA-A 1st Class`, `Youssef QA-A Test` and lesson |
| D | Desktop | German | 2, 3, 4, 6 | First administrator |

Before run C, the administrator creates a weekly schedule `QA-C Class` for `QA-A 1st Class` on today's weekday
(journey 4, first step) and waits for the generator run, so run C's attendance has a session of its own. In run
C, the parent links `Youssef QA-A Test`, the servant gets the servant role for `QA-A 1st Class`, and the homework
is created for run A's lesson.

Arabic checks: the layout runs right to left (navigation on the right, back arrows pointing right), numbers
and dates are readable, nothing is cut off. German checks: the formal "Sie", no English left on the screen.

### Fictional data (run A; the other runs replace `A` with their letter)

| Who or what | Value |
| --- | --- |
| Service | `QA-A Sunday School` |
| Stages | `QA-A 1st Class`, `QA-A 2nd Class` |
| Service without stages | `QA-A Choir` |
| Children in 1st Class | `Mina QA-A Test`, `Youssef QA-A Test` |
| Choir member | `Maria QA-A Test` |
| Visitor | `Kirollos QA-A Visitor` |
| Parent (registers) | `Hanna QA-A Parent`, `qa-a-parent@example.org`, child `Mina QA-A Test` |
| Servant (registers) | `Daniel QA-A Servant`, `qa-a-servant@example.org`, serves in 1st Class |
| Declined registration | `Bishoy QA-A Declined`, `qa-a-declined@example.org` |
| Passwords | A new random password per account, kept only by the tester |

## Journeys

### 1. Sign in and out, privacy notice, forgot password, one-time password link

| Step | Expected result |
| --- | --- |
| Open `https://app.khmk-koeln.com` without a session | The sign-in page opens over HTTPS; `http://` redirects to `https://` |
| Open the privacy notice from the sign-in page | The privacy notice opens, readable without signing in, marked as a draft if the final text is not approved |
| Sign in as the first administrator with a wrong password | "Wrong email or password", without saying which; no session |
| Sign in with the right password | The staff home (attendance overview) opens; the account menu shows "QA Admin" |
| Sign out | The sign-in page opens; the browser's back button shows no staff data |
| Open "Forgot password?" | A page explains that the church office hands out a one-time link; there is no form and no promise of an email |
| On the server, run `bin/console app:user:password-link qa-admin@example.org` | It prints a link valid for 24 hours; nothing about it appears in the logs (spot check 4) |
| Open the link, set a new password, sign in with it | The password is saved; signing in with the old one fails, with the new one succeeds |
| Open the same link again | "This link no longer works; ask the church office for a new one" |

### 2. Service, stages, people, roster; a service without stages

| Step | Expected result |
| --- | --- |
| As the administrator, create the service `QA-A Sunday School` | It appears in the services list with its colour |
| Add the stages `QA-A 1st Class` and `QA-A 2nd Class` | Both appear on the service in this order |
| Add `Mina QA-A Test` and `Youssef QA-A Test` to 1st Class | Both appear in Members with "1st Class"; the stage shows 2 members |
| Add `Mina QA-A Test` a second time | A duplicate warning names the existing person; cancelling creates nothing |
| Create the service `QA-A Choir` without stages and add `Maria QA-A Test` to its own roster | The choir shows 1 member on the service roster and no stages |

### 3. Registration: parent and servant approved, a third declined

| Step | Expected result |
| --- | --- |
| Signed out, register as `Hanna QA-A Parent` (parent; note "Mina QA-A Test, 1st Class"), accepting the privacy notice | The thank-you page says the church office tells her when the account is ready; no email promised |
| Register as `Daniel QA-A Servant` (servant; service `QA-A Sunday School`) | The same thank-you page |
| Register as `Bishoy QA-A Declined` (parent) | The same thank-you page |
| Register again with `qa-a-parent@example.org` | The same thank-you page; no second request appears (no hint that the email exists) |
| Sign in as the parent | The waiting page: the account is waiting for approval; no portal data |
| As the administrator, open Settings › Account requests | Three requests with their notes |
| Approve the parent, linking `Mina QA-A Test` | The request disappears; the parent appears in parent access |
| Approve the servant with the servant role for `QA-A 1st Class` | The request disappears |
| Decline `Bishoy QA-A Declined` | The request disappears; signing in with that email says "Wrong email or password" |
| Sign in as the parent | The parent portal shows Mina |
| Sign in as the servant | The staff home; Members shows only the people of 1st Class |

### 4. Schedule, generated session, cancel, move, a planned date canceled from the agenda

| Step | Expected result |
| --- | --- |
| As the administrator, create a weekly schedule `QA-A Class` for 1st Class on today's weekday, starting in at least two hours, roster "expected" | The schedule shows its next planned dates, the first one today |
| Create a second weekly schedule `QA-A Extra` for 1st Class, also today, an hour later | Its next planned date is today too |
| Wait for the next generator run (minute 5 of the hour) | The agenda shows two sessions for today, no longer only planned dates |
| Cancel today's `QA-A Extra` session with the reason "QA test" | It shows as canceled with the reason; attendance cannot be opened for it |
| Move today's `QA-A Class` session 30 minutes later | The agenda shows the new time; the schedule is unchanged |
| On the agenda, cancel next week's planned date of `QA-A Class` | That date shows as canceled; the week after still shows a planned date |

### 5. Phone attendance from the staff home

| Step | Expected result |
| --- | --- |
| As the servant, on the phone, open the staff home | Today's `QA-A Class` session is listed |
| Open attendance | The roster lists Mina and Youssef, both not marked; the counter shows 0 of 2 |
| Mark Mina present | Mina is marked; the counter shows 1 of 2 |
| Mark Youssef present, then remove the mark with the reason "Marked by mistake" | Youssef is not marked again; the counter shows 1 of 2 |
| Add the visitor `Kirollos QA-A Visitor`, who is not on the roster, and mark him present | He appears as not on the roster; 2 present, 1 of 2 from the roster |
| Close attendance and confirm | The summary shows 2 present, 1 of 2 from the roster, 1 not on the roster; marking is no longer possible |
| As the administrator (leader rights), reopen the session | Marking is possible again; the history shows "reopened" with who and when |
| Close it again | The summary shows the same numbers |

### 6. Lesson ready, then taught

| Step | Expected result |
| --- | --- |
| As the servant, create a lesson `QA-A The Good Samaritan` for today's `QA-A Class` meeting, passage "Luke 10:25–37", with yourself responsible | The lesson is a draft in the lessons list for today |
| Mark it ready | Status "ready" |
| Mark it taught | Status "taught"; this works because the teaching date is today |

### 7. Homework: published, found by the parent, done and undone, progress for the servant

| Step | Expected result |
| --- | --- |
| As the servant, create homework `QA-A Learn the memory verse` for the lesson, due in a week, and leave it as a draft | It shows as a draft; the parent's portal does not show it (spot check 3) |
| Publish it | Status "published" |
| As the parent, open Mina's Homework page | The task is listed as to do, with the due date and the lesson's passage |
| Open the task and mark it done | It shows as done, with when and by whom |
| Undo | It shows as to do again |
| Mark it done again | Done |
| As the servant, open the homework's progress | Mina: done, by Hanna QA-A Parent; Youssef: not done; 1 of 2 |

## Privacy spot checks

Run once after run B, with the accounts of runs A and B.

| # | Step | Expected result |
| --- | --- | --- |
| 1 | As parent A, open Mina B's homework page by putting her id into the address (from run B's URLs) | The same not-found page as for an id that does not exist |
| 2 | As servant A, open Members and a person page of run B (by id in the address) | Members lists only 1st Class of run A; the person page is not found or forbidden; the name search shows names and groups only |
| 3 | As parent A, look for a draft homework of run A, then open a staff page by address (attendance, members, settings) | No draft is visible; staff pages send the parent back to the portal or show not-found |
| 4 | On the server, search the API and proxy logs of the whole test (`docker compose logs`, the proxy's access log) for every fictional name, email, search term and the password-link token | No match. A match is a bug against #28 |

## Results

Each cell gets "Pass" or links to its bug issues.

| Journey | A: English, phone | B: English, desktop | C: Arabic, phone | D: German, desktop |
| --- | --- | --- | --- | --- |
| 1 Sign in and out | | | | – |
| 2 Services and people | | | – | |
| 3 Registration | | | | |
| 4 Schedules and sessions | | | – | |
| 5 Phone attendance | | | | – |
| 6 Lesson | | | – | |
| 7 Homework | | | | – |

| Spot check | Result |
| --- | --- |
| 1 Another family's child | |
| 2 Another service's people | |
| 3 Drafts and staff pages for parents | |
| 4 Logs without personal data | |

## Sign-off

| Name | Role | Date | Signature |
| --- | --- | --- | --- |
| | Project lead | | |
| | Church representative | | |
