# AGENTS.md — taskbridge-filament-3

For full context on this package, read @README.md. For the core package rules, read @../laravel-taskbridge/AGENTS.md.

## Commands

Run after every code change — must pass before finishing:

```bash
./vendor/bin/pint   # code style (default ruleset)
```

If `vendor/` is missing, run `composer install` first.

## Git

**Never create commits unless explicitly requested by the user.**

## Rules

**Interface names are final.** The four optional interfaces from the core package are: `RunsConditionally`, `HasGroup`, `HasCustomLabel`, `ReportsTaskOutput`. Do not use or reference the old names (`ConditionalJob`, `GroupedJob`, `LabeledJob`, `ReportsOutput`). Do not reference `ScheduledJob` — it no longer exists.

**`ReportsTaskOutput` requires `reportOutput()`.** The interface now declares `reportOutput(array $metadata): void` — it is no longer a marker. Add the `HasJobOutput` trait to satisfy it:
```php
class ImportProducts implements ReportsTaskOutput, ShouldQueue
{
    use HasJobOutput; // satisfies reportOutput()
}
```

**Always use enum cases in column closures.** `RunStatus` and `TriggeredBy` are Eloquent-cast enums. Filament passes the cast value, not a string:
```php
// correct
->color(fn (RunStatus $state) => $state->color())
// wrong — throws TypeError at runtime
->color(fn (string $state) => ...)
```

**Never interpolate enum instances as strings.** Always call `->label()`:
```php
// correct
'Status: ' . $run->status->label()
// wrong — throws: Object of class RunStatus could not be converted to string
"Status: {$run->status}"
```

**Output column always uses `JobOutput::fromArray()`.** The `output` column is a PHP array (Eloquent JSON cast). Never access keys directly in column definitions:
```php
->formatStateUsing(fn (?array $state) => $state ? JobOutput::fromArray($state)->label() : null)
```

**`resolveLabel()` and `resolveGroup()` are the single source of truth.** Both label and group fallback logic lives in `ScheduledJobResource::resolveLabel()` and `resolveGroup()`. Use these helpers everywhere — do not inline the detection logic. Priority order inside each helper: `#[SchedulableJob]` attribute → interface method → auto-derived default. Do not change this order.

**The job picker is a Livewire modal — not a Select.** The Create form uses `Hidden::make('class')` to hold the selected class value and `Forms\Components\Livewire::make(JobPickerModal::class)` to render the interactive picker. Do not replace it with a `Select` or any other form field. The picker is registered as `taskbridge-job-picker` in the service provider.

**Incompatible jobs are visible in the picker with a warning — not hidden.** `JobPickerModal` merges registered classes (compatible) with all discovered classes (including those filtered out by `hasSimpleConstructor`). Incompatible jobs are shown as non-clickable cards with a red warning listing the offending parameters. Do not filter them out of the picker — showing them with an explanation is intentional.

**`CreateScheduledJob::onJobSelected()` handles job selection side-effects.** The `#[On('taskbridge-job-selected')]` handler updates `$this->data['class']`, `_identifier_hint`, `cron_override`, and `group` — the same side-effects the old `Select::afterStateUpdated` callback performed. If you need to react to job selection, do it here.

**Validation on empty class uses `Halt` + `Notification` + a picker error event.** `mutateFormDataBeforeCreate` guards against an empty `class` value: it sends a danger notification, dispatches `taskbridge-picker-error` (which turns the Browse button red), then throws `Filament\Support\Exceptions\Halt`. Never let an empty class reach `JobFormBuilder::resolveArguments()`.

**`JobPickerSize` enum controls modal width and item grid columns.** Values: `Medium` (48rem, 2 cols), `Large` (72rem, 3 cols), `Xl` (90rem, 4 cols). Set via `TaskBridgePlugin::make()->jobPickerSize(JobPickerSize::Large)`. Width and column count are derived from `$size->maxWidth()` and `$size->columns()` — do not hardcode these values in the blade.

**The Create page has header actions.** `CreateScheduledJob::getHeaderActions()` returns Cancel, Save & create another, and Create. These mirror the bottom form actions and are shown at the top of the page.

**`TaskBridgePlugin::get()` is the only way to read plugin config.** Never inject the plugin via constructor or instantiate it with `new`. During tests it falls back to defaults automatically.

**Always resolve model classes via config:**
```php
config('taskbridge.models.scheduled_job', ScheduledJob::class)
```

**Row actions call `TaskBridge::run()`, never `dispatch()` directly:**
```php
// correct
$run = TaskBridge::run($record->class, force: true);
// wrong
dispatch(new ($record->class));
```

**The driver method is `getEventBridge()`, not `getDriver()`.**

**No `BadgeColumn`.** Filament 3 uses `TextColumn->badge()` only.

**One-time jobs are read-only in the table.** Run now, Dry run, and Edit row actions are hidden for one-time jobs (`$record->isOnce()`). The enabled toggle is also disabled for them. Never show `ScheduleOnceAction` as a row action — it was removed from the table entirely.

**`ScheduleOnceAction` is not a table row action.** One-time scheduling is done from outside the table (e.g. a header action or a separate flow). Do not re-add it to `buildRowActions()`.

**Bool constructor parameters render as a Select, not a Toggle.** The options are `['1' => 'True', '0' => 'False']`. Never use `Toggle` for constructor argument fields:
```php
// correct
Select::make($fieldName)->options(['1' => 'True', '0' => 'False'])
// wrong
Toggle::make($fieldName)
```

**The Constructor Arguments section is always visible.** It never conditionally hides itself. Instead it shows one of three states based on `$get('class')`:
1. No class selected → `Placeholder` with "No job has been selected."
2. Class has no scalar params → `Placeholder` with "This job doesn't have any arguments."
3. Class has scalar params → the fields from `JobFormBuilder::buildFields()`

**Form layout: Constructor Arguments (span 2) + Retry Policy (span 1) in a Grid::make(3).** Always place these two sections together in this grid. Constructor Arguments comes first (left, 66%), Retry Policy comes second (right, 33%).

**`buildClassOptions()` uses `ScheduledJob::recurring()` scope.** When checking for already-registered ("taken") classes, scope the query to recurring jobs only — one-time job rows must not mark a class as taken.

**The `_status_dot` column is a `ColorColumn`.** It derives its color from `$record->last_status?->color() ?? 'gray'`. It has no label and a fixed width of `4px`. It is the leftmost column in the table.

**Activate/Deactivate on the view page use `$this->record = $this->record->fresh()` to refresh.** `refreshFormData()` does not exist on `ViewRecord`. After the action runs, reload the record with `->fresh()` to reflect the new enabled state in the infolist.

**`ToggleColumn::afterStateUpdated` must use the injected `bool $state` parameter.** Never read `$record->enabled` inside `afterStateUpdated` — the record has not been updated yet at that point. Use the injected `bool $state` value. Additionally, wrap the AWS enable/disable call in a `try/catch(\Throwable)` so that API failures are surfaced as a Filament notification rather than reverting the toggle silently. This is consistent with `EditScheduledJob::afterSave()`:
```php
->afterStateUpdated(function (bool $state, ScheduledJob $record) {
    try {
        $state ? TaskBridge::enable($record->class) : TaskBridge::disable($record->class);
    } catch (\Throwable $e) {
        Notification::make()->danger()->title('AWS error')->body($e->getMessage())->send();
    }
})
```

**The Tools ActionGroup must stay grouped in `ListScheduledJobs::getHeaderActions()`.** `SyncAction`, `ValidateJobsAction`, and `ImportSchedulesAction` are grouped together under a single `ActionGroup` (outlined, gray, wrench icon) labelled "Tools". Schedule Once and Add Job remain as standalone header actions. Do not split the three tool actions back into standalone header actions.

**`ImportSchedulesAction` must reuse core package helpers.** `ImportSchedulesAction` calls `ImportSchedulesCommand::parseEntry()` and `ImportSchedulesCommand::validateArguments()` from the core package. Never duplicate this validation logic inside the Filament package.

## Further reading

- @README.md — plugin configuration and all available options
- @../laravel-taskbridge/docs/architecture.md — model schemas, enum values, contracts, execution paths
- @../laravel-taskbridge/AGENTS.md — core package rules that also apply here
