# ATA Cron — Queue & Scheduler

D-6/D-7: Scheduler = "when", Queue = "execution with retry".

## Files
- `Runner.php` — WP-Cron queue runner. Atomic claim via `lock_token`, bounded jobs/run, exponential backoff retry, expired-lock cleanup.
- `SchedulerAdapter.php` — Prefers Action Scheduler when available, falls back to WP-Cron. Handles recurring + one-shot scheduling.

## Design
- `Runner::HOOK` = `ata_due_queue` (1-minute custom schedule).
- `MAX_JOBS_PER_RUN = 5`, `LOCK_TTL = 300` (5 min).
- Atomic claim: `UPDATE ... WHERE status='pending' AND locked_at IS NULL` with `lock_token`.
- `SchedulerAdapter::maybeSwitchToActionScheduler()` runs on `init`; if AS is present it unschedules the WP-Cron event and reschedules via `as_schedule_recurring_event`.
- `SchedulerAdapter::scheduleOneShot()` chooses AS vs WP-Cron based on availability.

## Action Scheduler evaluation result
- **Available**: yes (function_exists check).
- **Used for**: recurring queue drain + one-shot jobs.
- **Fallback**: WP-Cron when AS is not installed.
- **Status**: adapter is a thin wrapper; Runner logic unchanged.