# Falcon Time Tracker

Small web app for tracking employees' working time. Built as a Falcon Jobster test assignment.

**Stack:** PHP 7+ (no framework), JSON file storage, vanilla HTML/CSS/JS.

## Features

- Add employees (fictional data only)
- Start / stop a timer with a task description (one active timer per employee)
- Report: entries + total time per employee for a selected period
- Data persists in `data/db.json` across application restarts

## How to run

### Option A — PHP built-in server (recommended for local demo)

```bash
cd falcon-time-tracker   # or this folder
php -S localhost:8080
```

Open [http://localhost:8080](http://localhost:8080).

### Option B — Apache / existing PHP host

Copy this folder into a web-accessible directory and open `index.html` (ensure PHP can write to `data/`).

`data/` must be writable by the web server user.

## Tests

```bash
php tests/run.php
```

Covered scenarios:

- Adding employees (validation + duplicates)
- Start / stop timer
- **Restarting the timer** (stop → start again; reject concurrent start)
- **Empty report** (period with no entries → zero totals)
- Persistence after application restart (new process, same store file)
- Report filter by employee and by date range

## API (for reference)

| Method | Action | Body / query |
|--------|--------|--------------|
| `GET` | `api.php?action=employees` | — |
| `POST` | `api.php?action=employees` | `{"name":"..."}` |
| `POST` | `api.php?action=timer/start` | `{"employee_id":1,"task":"..."}` |
| `POST` | `api.php?action=timer/stop` | `{"employee_id":1}` |
| `GET` | `api.php?action=report&from=YYYY-MM-DD&to=YYYY-MM-DD&employee_id=` | optional filter |

## AI tools and models used

| Tool | Role |
|------|------|
| **Cursor** (Agent mode) | Primary environment: scaffolding, code edits, running tests |
| **Composer** (Cursor agent model) | Generated app structure, PHP domain logic, UI, tests, README |

No separate ChatGPT / Claude web chats were required for this assignment; generation and fixes happened inside Cursor.

### Example prompts / queries used

1. **Scaffolding**
   > Create from scratch a small PHP web app for employee work time tracking: add employees, start/stop timer with task description, report totals by period, JSON persistence, simple UI, and CLI tests including timer restart and empty report.

2. **Domain rules**
   > Only one running timer per employee. Starting again while a timer is active must fail clearly; after stop, starting again must work (restart flow).

3. **Persistence check**
   > Add a test that writes data, constructs a new Store/TimeTracker on the same file (simulating app restart), and asserts employees and entries are still there.

4. **UI polish**
   > Keep the interface simple: employees panel, timer panel, report table with duration totals. No auth, fictional names only.

5. **Bug hunt**
   > Review TimeTracker report logic for empty periods and running timers; ensure grand_total_seconds is 0 when there are no entries.

### Errors found in generated code and how they were fixed

1. **`padStart` used before polyfill in `app.js`**  
   The date helper ran before the polyfill was defined, which could break on older browsers. **Fix:** move the polyfill to the top of the IIFE.

2. **Concurrent timer not blocked clearly**  
   Early draft risked overlapping open entries. **Fix:** `startTimer()` checks `getActiveEntry()` and throws if one is already running; covered by the “restarting the timer” test.

3. **Empty report ambiguity**  
   Totals for all employees with `0` seconds looked like “data” even when nothing happened. **Fix:** UI shows an explicit “Empty report” message when there are no entries; tests assert `entries === []` and `grand_total_seconds === 0`.

4. **Store write safety**  
   Direct `file_put_contents` on the live file could leave a half-written JSON on crash. **Fix:** write to a `.tmp` file then `rename()` into place.

## Project layout

```
├── index.html          # UI
├── api.php             # JSON API
├── assets/
│   ├── app.js
│   └── style.css
├── lib/
│   ├── Store.php       # JSON persistence
│   └── TimeTracker.php # Domain logic
├── data/               # db.json created at runtime (gitignored)
├── tests/run.php
└── README.md
```

## License

MIT — fictional demo data only; not for production HR use.
