# taskbridge-filament-3

Filament v3 admin panel integration for [laravel-taskbridge](../laravel-taskbridge/README.md). Provides a complete UI for managing scheduled jobs, viewing run history, and triggering manual executions — all without touching AWS directly.

> **Filament v4 support is coming soon.** This package targets Filament v3. A dedicated `codetechnl/laravel-taskbridge-filament-4` package is in development.

## Features

- **Scheduled Jobs CRUD** — create, edit, view, and delete scheduled jobs; changes auto-sync to EventBridge
- **One-time job visibility** — one-time schedules appear in the table alongside recurring jobs with an "once" badge; records are kept until pruned
- **Type filter** — filter the table by job type: all, one-time only, or recurring only
- **Status dot column** — color-coded indicator showing last run status at a glance
- **Dynamic constructor argument fields** — scalar constructor parameters (`bool`, `int`, `float`, `string`) render as typed inputs in create/edit forms and in action modals
- **Run now / Dry run actions** — trigger immediate or simulated execution from the table
- **Human-readable cron descriptions** — cron expressions are translated to plain English (e.g. `0 8 * * 1` → "Every Monday at 08:00") in the table tooltip and the view page
- **Activate / Deactivate** — enable or disable a job directly from the view page
- **Run Logs** — full audit history of every execution with status, duration, trigger type, and structured output
- **Dashboard widget** — summary of total, active, disabled, and recently-failed jobs

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- Filament 3.2+
- `codetechnl/laravel-taskbridge` (installed and configured)

## Installation

```bash
composer require codetechnl/laravel-taskbridge-filament-3
```

## Register the plugin

Add the plugin to your Filament panel provider:

```php
use CodeTechNL\TaskBridgeFilament\TaskBridgePlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            TaskBridgePlugin::make(),
        ]);
}
```

That's it. The plugin automatically registers the **Scheduled Jobs** resource, the **Run Logs** resource, and the **TaskBridge stats widget**.

## What you get

### Scheduled Jobs resource

A full CRUD interface for your registered jobs:

- **Create** — select a job class from your registered jobs, configure queue connection, cron expression, retry policy, description, and enable/disable
- **Edit** — update any settings; saved changes auto-sync to EventBridge
- **View** — detailed job info (class, schedule, status, description, group) with activate/deactivate header actions and inline run history
- **Filters** — filter by group, enabled state, last status, and job type (one-time / recurring)

**Row actions available on every job:**

| Action | Description |
|--------|-------------|
| Run now | Immediately executes the job (bypasses enabled / shouldRun). Hidden for one-time jobs. |
| Dry run | Calls handle() with Bus::fake() — no real queue dispatches. Hidden for one-time jobs. |
| Edit | Edit job settings. Hidden for one-time jobs. |
| View | Open the detail page |
| Delete | Remove from database and EventBridge |

> One-time jobs do not support Run now, Dry run, or Edit — they are read-only once created and self-destruct on EventBridge after firing.

When a job has scalar constructor parameters, **Run now** and **Dry run** each render an input field per parameter inside their confirmation modal. Values are type-cast to the declared PHP type before the job is executed.

**Bulk actions:** Enable selected, Disable selected, Delete selected

**Header actions:**

| Action | Location | Description |
|--------|----------|-------------|
| Sync | Tools dropdown | Push all enabled jobs to AWS EventBridge Scheduler |
| Validate | Tools dropdown | Check that all registered job classes exist and can be loaded |
| Import | Tools dropdown | Import jobs from `taskbridge.schedules` config into the database |
| Schedule Once | Standalone | Schedule a job to run once at a specific date/time |
| Add Job | Standalone | Open the create form to register a new recurring job |

Sync, Validate, and Import are grouped under a **Tools** button (outlined, gray, wrench icon). Schedule Once and Add Job remain as standalone header actions.

### Table columns

| Column | Description |
|--------|-------------|
| Status dot | Color-coded dot showing last run status (gray = never run) |
| Job | Label + group description |
| Schedule | Cron expression badge (or "once" badge for one-time jobs). Hover to see human-readable description. |
| Enabled | Toggle. Disabled for one-time jobs. |
| Last Run | Relative time since last execution |
| Status | Last run status badge |
| Next Run | Next scheduled time (or the one-time datetime for once jobs) |

### View page

The view page shows two sections side by side:

- **Job** — class name, whether the class exists, description, identifier, group
- **Schedule** — job type badge (One-time / Recurring), cron expression with human-readable description, enabled status

Header actions:
- **Activate** — enables the job and syncs to EventBridge (visible when disabled, recurring only)
- **Deactivate** — disables the job and removes from EventBridge (visible when enabled, recurring only)
- **Edit** — opens the edit form (recurring only)

The **Run History** relation manager is shown inline at the bottom.

### Run Logs resource

A read-only audit log of every job execution. Columns include status, trigger type, duration, jobs dispatched, and structured output.

Filters: job name, identifier, status, trigger type.

Row action: **Output** — opens a modal with the full structured output when a job reported metadata.

### Dashboard widget

A stats overview widget showing:
- Total jobs
- Active (enabled) jobs
- Disabled jobs
- Failed runs in the last 24 hours (shown in red when > 0)

## Plugin configuration

All options are set fluently on `TaskBridgePlugin::make()`:

```php
use CodeTechNL\TaskBridgeFilament\Enums\JobPickerSize;

TaskBridgePlugin::make()
    ->navigationGroup('Infrastructure')
    ->navigationLabel('Scheduler')
    ->navigationIcon('heroicon-o-clock')
    ->navigationSort(50)
    ->slug('scheduler')
    ->heading('Scheduled Jobs')
    ->subheading('Manage your AWS EventBridge schedules')
    ->paginationPageOptions([10, 25, 50])
    ->defaultPaginationPageOption(25)
    ->groupActions()                         // collapse row actions into a dropdown
    ->preventDuplicates(true)                // block the same class being registered twice
    ->jobPickerSize(JobPickerSize::Large)    // Medium (default), Large, or Xl
    ->withoutWidget()                        // remove the stats widget
    ->withoutRunLog()                        // remove the Run Logs page
    ->runLogNavigationLabel('Job History')
    ->runLogSlug('job-history')
    ->runLogPaginationPageOptions([25, 50])
```

### All available options

> **Navigation nesting:** Filament v3 supports flat navigation only (Group → Items). There is no support for nested groups (e.g. System → Task Bridge → Items). Use a single group name.

| Method | Default | Description |
|--------|---------|-------------|
| `navigationGroup(string)` | `'Task Bridge'` | Sidebar group label for Scheduled Jobs and Run Logs |
| `dashboardNavigationGroup(string)` | `'Task Bridge'` | Sidebar group label for the Dashboard |
| `navigationLabel(string)` | `'Scheduled Jobs'` | Sidebar item label |
| `navigationIcon(string)` | `heroicon-o-clock` | Sidebar icon |
| `navigationSort(int)` | `2` | Sidebar sort order (Dashboard defaults to 1, Run Logs is always +1) |
| `dashboardNavigationSort(int)` | `1` | Sort order for the Dashboard item |
| `slug(string)` | `scheduled-jobs` | URL path for the resource |
| `heading(string)` | `'Scheduled Jobs'` | H1 on the list page |
| `subheading(string)` | `null` | Subtitle below H1 |
| `preventDuplicates(bool)` | `true` | Block duplicate job registrations |
| `groupActions(bool)` | `false` | Collapse row actions into a dropdown |
| `jobPickerSize(JobPickerSize)` | `JobPickerSize::Medium` | Size of the job picker modal. `Medium` = 48rem / 2 cols, `Large` = 72rem / 3 cols, `Xl` = 90rem / 4 cols |
| `paginationPageOptions(array)` | `[25, 50, 100]` | Page size options |
| `defaultPaginationPageOption(int)` | `25` | Default page size |
| `withoutWidget()` | — | Do not register the stats widget |
| `withoutRunLog()` | — | Do not register the Run Logs page |
| `runLogNavigationLabel(string)` | `'Run Logs'` | Run Logs sidebar label |
| `runLogNavigationIcon(string)` | `heroicon-o-list-bullet` | Run Logs sidebar icon |
| `runLogSlug(string)` | `scheduled-job-runs` | Run Logs URL path |
| `runLogHeading(string)` | `'Run Logs'` | Run Logs H1 |
| `runLogPaginationPageOptions(array)` | `[25, 50, 100]` | Run Logs page size options |
| `runLogDefaultPaginationPageOption(int)` | `25` | Run Logs default page size |
| `policy(string)` | `null` | Custom Filament policy class |

## Authorisation

Pass a policy class to restrict access:

```php
TaskBridgePlugin::make()
    ->policy(App\Policies\ScheduledJobPolicy::class)
```

The policy is applied to `ScheduledJobResource`. Standard Filament policy methods apply: `viewAny`, `create`, `update`, `delete`, etc.

## Creating a job in the UI

### Job picker modal

On the Create page, clicking **Browse jobs** opens a modal that lists all discovered job classes grouped by their group. Use the search box to filter by name or group.

- **Compatible jobs** (scalar constructor only) — shown as clickable cards. Click one to select it.
- **Incompatible jobs** (non-scalar constructor parameters) — shown as disabled cards with a red warning that lists the offending parameters. These cannot be scheduled from the UI without code changes.

Once a job is selected, TaskBridge automatically pre-fills:

- **Identifier** — derived from the class name + name prefix
- **Cron expression** — from `#[SchedulableJob(cron:)]` if set, otherwise from `HasPredefinedCronExpression::cronExpression()` if implemented
- **Group** — from `#[SchedulableJob(group:)]` if set, otherwise from `HasGroup::group()` if implemented, otherwise from the folder name

All of these can be edited before saving. The cron field is required only when the job class does not define a default via the attribute or the interface.

Submitting the form without selecting a job shows a danger notification and highlights the Browse button in red.

The Create page also shows **Cancel**, **Save & create another**, and **Create** actions in the page header, mirroring the bottom form buttons.

### Constructor arguments in the create/edit form

The **Constructor Arguments** section is always visible in the create and edit forms. Its content depends on the selected job:

- **No job selected** — shows "No job has been selected."
- **Job has no parameters** — shows "This job doesn't have any arguments."
- **Job has scalar parameters** — renders a typed input per parameter

The section sits left (2/3 width) in a grid alongside the **Retry Policy** section (1/3 width).

When a job has scalar constructor parameters, fill in the values in the Constructor Arguments section. They are stored on the job record and baked into the SQS payload every time EventBridge fires the recurring schedule.

The Edit form pre-fills the stored values so you can adjust them at any time. Saving re-syncs the updated payload to EventBridge automatically.

**Field types rendered per parameter:**

| PHP type | Filament field |
|----------|---------------|
| `bool` | Select (options: True / False) |
| `int` | Numeric text input (integer) |
| `float` | Numeric text input |
| `string` / untyped | Text input |
| `?type` (required, no default) | Same field as its base type, **marked required** — empty submission sends `null` to the job |
| `?type` with `= null` default | Same field, optional — empty submission sends `null`; a helper text hint is shown |

## Labels and groups

Without any configuration, TaskBridge derives readable values automatically:

- **Label**: `SendDailyReport` → `"Send daily report"`
- **Group**: `App\Jobs\Reporting\SendDailyReport` → `"Reporting"` (from folder)

### Override via attribute

The `#[SchedulableJob]` attribute is the most concise way to set label, group, and cron together:

```php
use CodeTechNL\TaskBridge\Attributes\SchedulableJob;

#[SchedulableJob(name: 'Daily Report — Finance', group: 'Finance', cron: '0 8 * * 1-5')]
class SendDailyReport implements ShouldQueue { ... }
```

### Override via interfaces

The interfaces still work and are the right choice when the label or group needs runtime logic:

```php
use CodeTechNL\TaskBridge\Contracts\HasCustomLabel;
use CodeTechNL\TaskBridge\Contracts\HasGroup;

class SendDailyReport implements HasCustomLabel, HasGroup, ShouldQueue
{
    public function taskLabel(): string
    {
        return 'Daily Report — Finance';
    }

    public function group(): string
    {
        return 'Finance'; // Overrides the folder-based detection.
    }
}
```

**Priority order:** `#[SchedulableJob]` attribute → interface method → auto-derived default. When both the attribute and an interface are present, the attribute wins.

## Viewing structured job output

When a job implements `ReportsTaskOutput` and uses the `HasJobOutput` trait, execution metadata is stored on the run record. In the Run Logs table, the **Output** action opens a modal showing:

- Status badge (Success / Error / Warning / Info)
- Message text
- Key/value metadata table

Example:

```
Status:  Success
Message: Import complete

processed  | 1 420
skipped    | 38
duration   | 2.1s
```

## Run Logs in the job view

Opening a job's detail page (View) shows the run history inline via the **Run History** relation manager. Same columns and actions as the standalone Run Logs page.

---

*This package was fully built with [Claude Code](https://claude.ai/claude-code).*
