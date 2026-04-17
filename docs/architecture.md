# taskbridge-filament-3 — Architecture Reference

This document describes the complete internal structure of the Filament 3 UI package for TaskBridge. Read alongside `packages/laravel-taskbridge/docs/architecture.md` for the full picture.

---

## Purpose

Provides a full Filament 3 admin interface for `codetechnl/laravel-taskbridge` (`codetechnl/laravel-taskbridge-filament-3`). This includes a resource for managing scheduled jobs, a read-only run log, a stats widget, and table row actions for manual execution and sync.

---

## Plugin system

### TaskBridgePlugin

Implements `Filament\Contracts\Plugin`. Registered on a panel via:

```php
$panel->plugin(TaskBridgePlugin::make());
```

The plugin stores all UI configuration as private properties with fluent setters. It registers resources and widgets with the panel in `register()`, and stores a static `$instance` reference in `boot()`.

`TaskBridgePlugin::get()` is the singleton accessor used by resource static methods (which are called statically by Filament's routing — they cannot receive constructor injection). Fallback behaviour: if Filament is not booted (e.g. during tests), `get()` returns `new static` with all defaults.

---

## Resources

### ScheduledJobResource

Full CRUD resource for managing scheduled jobs.

**Form fields (Create page):**
- `class` — `Hidden::make('class')` holds the selected FQCN. `Forms\Components\Livewire::make(JobPickerModal::class)` renders the interactive picker modal. When a job is selected, `CreateScheduledJob::onJobSelected()` (via `#[On('taskbridge-job-selected')]`) updates the hidden field and auto-populates `identifier`, `cron_override`, and `group`.
- On the **Edit page**, `class` is shown as a disabled `TextInput` (read-only after creation).
- `queue_connection` — `Select` built from `config('queue.connections')`, filtered to SQS-only entries
- `cron_expression` — `TextInput` with `CronTranslator::isValid()` validation; shows human-readable description via `CronTranslator::describe()`
- `cron_override` — nullable `TextInput` with same cron validation
- `retry_maximum_event_age_seconds` — nullable integer (60–86400)
- `retry_maximum_retry_attempts` — nullable integer (0–185)
- `enabled` — `Toggle`

**Table columns:**
- Status colour dot (custom `ViewColumn`)
- Job label (resolved via `resolveLabel()` — checks `LabeledJob`, falls back to class basename)
- Cron badge showing `effective_cron`
- Enabled toggle (inline)
- Last run timestamp
- Last status badge (RunStatus enum)
- Next run timestamp (computed from cron)

**Table filters:** group, enabled/disabled, last status

**Table actions:** `RunJobAction`, `DryRunJobAction`, Edit, View, Delete

**Bulk actions:** Enable selected, Disable selected, Delete selected

**Header actions:** An `ActionGroup` labelled "Tools" (outlined, gray, wrench icon) containing `SyncAction`, `ValidateJobsAction`, and `ImportSchedulesAction`; plus standalone `ScheduleOnceAction` and the Add Job (Create) action.

**Static helpers:**
- `resolveLabel(string $class): string` — returns `taskLabel()` if `LabeledJob`, otherwise `class_basename()`

### ScheduledJobRunResource

Read-only resource for the run log. No create/edit pages.

**Table columns:**
- Job name (via `scheduledJob.class` + `resolveLabel`) with group as description
- Status badge (RunStatus enum via `->badge()`)
- Trigger badge (TriggeredBy enum via `->badge()`)
- Started at (datetime)
- Duration (formatted: `{n}ms` or `{n.nn}s`)
- Jobs dispatched (integer)
- Output badge (JobOutput::fromArray — status + color)

**Table filters:** job (by scheduled_job_id), identifier (via `whereHas`), status, triggered_by

**Table actions:** `view_output` — opens `output-detail` modal; only visible when `output` is non-null

**Pagination:** configured via `TaskBridgePlugin::get()->getRunLogPaginationPageOptions()`

---

## Pages

### ListScheduledJobs

Standard list page. Header actions: a "Tools" `ActionGroup` containing `SyncAction`, `ValidateJobsAction`, and `ImportSchedulesAction`; plus standalone `ScheduleOnceAction` and the Add Job (Create) action.

### CreateScheduledJob

Standard create page. On submit:
1. Validates that the class is not already registered (if `preventDuplicates = true`)
2. Auto-fills `identifier` from `ScheduledJob::identifierFromClass($class)`
3. After create, syncs the new job to EventBridge (`TaskBridge::getEventBridge()->sync(...)`)

### EditScheduledJob

Standard edit page with a Delete action. After save: re-syncs to EventBridge.

### ViewScheduledJob

View-only page with infolist sections showing all job fields plus `effective_cron`. Has `RunsRelationManager` to show run history inline.

### ListScheduledJobRuns

Standard list page (no create/edit). Shows run log with all filters.

---

## Relation Manager

### RunsRelationManager

Attached to `ViewScheduledJob`. Shows run history for the current job. Same columns as `ScheduledJobRunResource` (minus the job name column). Same `view_output` action.

---

## Actions

### RunJobAction

```php
RunJobAction::make()
```

Table row action. Calls `TaskBridge::run($record->class, force: true)`. On success, shows a Filament notification with status label and duration. On exception, shows a danger notification.

### DryRunJobAction

Same as `RunJobAction` but passes `dryRun: true`. The notification body includes `[DRY RUN]` in the description.

### SyncAction

Header action. Calls `TaskBridge::sync()`. Shows a success notification with created/updated/removed counts.

### ValidateJobsAction

Header action. Iterates all registered job classes. For each:
- Checks `class_exists()`
- Checks that the class can be loaded and reflected (e.g. via `ReflectionClass`)
Collects warnings and shows them in a notification.

### ImportSchedulesAction

Header action (part of the Tools `ActionGroup`). Imports jobs from `config('taskbridge.schedules')` into the database. Reuses `ImportSchedulesCommand::parseEntry()` and `ImportSchedulesCommand::validateArguments()` from the core package for validation — never duplicates that logic. Shows a success notification with a count of imported entries, or a warning/danger notification if any entries failed.

---

## Widget

### TaskBridgeWidget

Extends `Filament\Widgets\StatsOverviewWidget`. Displays four `Stat` cards:

| Stat | Query |
|------|-------|
| Total Jobs | `ScheduledJob::count()` |
| Active Jobs | `ScheduledJob::where('enabled', true)->count()` |
| Disabled Jobs | `ScheduledJob::where('enabled', false)->count()` |
| Failed (24h) | `ScheduledJobRun::where('status', RunStatus::Failed)->where('started_at', '>=', now()->subDay())->count()` |

The "Failed (24h)" card uses `danger` color when count > 0, `success` when 0.

---

## Views

### `taskbridge-filament::modals.output-detail`

Blade view for the output detail modal. Receives `$output` as a PHP array.

Layout:
1. Status badge (colour from `match($status)` in the view)
2. Message text (if non-empty)
3. Metadata table: key in left column (fixed 10rem width), value in right column. Arrays rendered via `json_encode(..., JSON_PRETTY_PRINT)` in a `<pre>` tag.

---

## Enum display pattern

Both `RunStatus` and `TriggeredBy` are displayed consistently across all table columns:

```php
Tables\Columns\TextColumn::make('status')
    ->badge()
    ->color(fn (RunStatus $state) => $state->color())
    ->formatStateUsing(fn (RunStatus $state) => $state->label())
```

The type hint must match the cast type. If the field is cast to `RunStatus`, the closure receives a `RunStatus` instance, not a string.

---

## Service provider

`TaskBridgeFilamentServiceProvider`:
- Loads views from `resources/views` with namespace `taskbridge-filament`
- Publishes views

No routes are registered — all routing is handled by Filament's resource system.

---

## Dependencies

| Package | Role |
|---------|------|
| `codetechnl/laravel-taskbridge` | Core models, enums, actions, events |
| `filament/filament` ^3.2 | Resource, Widget, Action, Notification base classes |

The Filament 3 package has no database migrations of its own — all tables are owned by `laravel-taskbridge`.
