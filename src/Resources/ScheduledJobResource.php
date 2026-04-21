<?php

namespace CodeTechNL\TaskBridgeFilament\Resources;

use CodeTechNL\TaskBridge\Contracts\HasCustomLabel;
use CodeTechNL\TaskBridge\Contracts\HasGroup;
use CodeTechNL\TaskBridge\Contracts\HasPredefinedCronExpression;
use CodeTechNL\TaskBridge\Contracts\HidesFromTaskCreation;
use CodeTechNL\TaskBridge\Enums\RunStatus;
use CodeTechNL\TaskBridge\Facades\TaskBridge;
use CodeTechNL\TaskBridge\Models\ScheduledJob;
use CodeTechNL\TaskBridge\Support\CronTranslator;
use CodeTechNL\TaskBridge\Support\JobInspector;
use CodeTechNL\TaskBridgeFilament\Actions\DryRunJobAction;
use CodeTechNL\TaskBridgeFilament\Actions\RunJobAction;
use CodeTechNL\TaskBridgeFilament\Livewire\JobPickerModal;
use CodeTechNL\TaskBridgeFilament\Resources\ScheduledJobResource\Pages\CreateScheduledJob;
use CodeTechNL\TaskBridgeFilament\Resources\ScheduledJobResource\Pages\EditScheduledJob;
use CodeTechNL\TaskBridgeFilament\Resources\ScheduledJobResource\Pages\ListScheduledJobs;
use CodeTechNL\TaskBridgeFilament\Resources\ScheduledJobResource\Pages\ViewScheduledJob;
use CodeTechNL\TaskBridgeFilament\Resources\ScheduledJobResource\RelationManagers\RunsRelationManager;
use CodeTechNL\TaskBridgeFilament\Support\JobFormBuilder;
use CodeTechNL\TaskBridgeFilament\TaskBridgePlugin;
use Filament\Actions;
use Filament\Forms;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use UnitEnum;

class ScheduledJobResource extends Resource
{
    protected static ?string $model = ScheduledJob::class;

    public static function getModel(): string
    {
        return config('taskbridge.models.scheduled_job', ScheduledJob::class);
    }

    // ── Navigation (all values delegated to TaskBridgePlugin) ─────────────────

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return TaskBridgePlugin::get()->getNavigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return TaskBridgePlugin::get()->getNavigationLabel();
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return TaskBridgePlugin::get()->getNavigationIcon();
    }

    public static function getNavigationSort(): ?int
    {
        return TaskBridgePlugin::get()->getNavigationSort();
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return TaskBridgePlugin::get()->getSlug();
    }

    public static function getModelLabel(): string
    {
        return 'Scheduled Job';
    }

    public static function getPluralModelLabel(): string
    {
        return TaskBridgePlugin::get()->getNavigationLabel();
    }

    // ── Form ──────────────────────────────────────────────────────────────────

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                Section::make('Job class')
                    ->schema([
                        // CREATE: job picker modal (Livewire component) + hidden class field
                        Forms\Components\Hidden::make('class')
                            ->visibleOn('create')
                            ->rules([
                                fn () => function (string $attribute, mixed $value, \Closure $fail) {
                                    if (! $value) {
                                        $fail('Please select a job.');

                                        return;
                                    }
                                    if (! TaskBridgePlugin::get()->shouldPreventDuplicates()) {
                                        return;
                                    }
                                    if (ScheduledJob::where('class', $value)->exists()) {
                                        $fail('This job is already registered. Edit the existing record instead.');
                                    }
                                },
                            ]),

                        Livewire::make(JobPickerModal::class, [
                            'size' => TaskBridgePlugin::get()->getJobPickerSize(),
                        ])
                            ->key('taskbridge-job-picker')
                            ->visibleOn('create'),

                        // EDIT: read-only display of the class
                        Forms\Components\TextInput::make('class')
                            ->label('Job class')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),

                        Forms\Components\TextInput::make('_identifier_hint')
                            ->label('Identifier')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Auto-derived from class name')
                            ->visibleOn('create'),

                        Forms\Components\TextInput::make('group')
                            ->label('Group')
                            ->placeholder('Auto-derived from folder structure')
                            ->maxLength(255)
                            ->nullable()
                            ->helperText('Organises jobs in the UI. Auto-detected from the job\'s folder; override freely.'),

                        Forms\Components\Select::make('queue_connection')
                            ->label('Queue connection')
                            ->options(fn () => self::buildQueueConnectionOptions())
                            ->required()
                            ->helperText('The SQS queue connection this job will be dispatched to.'),

                        Forms\Components\TextInput::make('cron_override')
                            ->label('Cron expression')
                            ->placeholder(fn (?ScheduledJob $record) => $record?->cron_expression ?? 'e.g. 0 3 * * *')
                            ->columnSpanFull()
                            ->helperText('Defines when this job runs. Overrides any default set in cronExpression(). '
                                .'Accepts 5-part standard cron (minute hour dom month dow) or 6-part AWS format adding year at the end.')
                            ->rules([
                                fn (Get $get) => function (string $attribute, mixed $value, \Closure $fail) use ($get) {
                                    if ($value) {
                                        if (! CronTranslator::isValid($value)) {
                                            $fail('Invalid cron expression. Use standard 5-part format: minute hour day-of-month month day-of-week.');
                                        }

                                        return;
                                    }

                                    // No value supplied — only an error when the class also has no default.
                                    $class = $get('class');
                                    if (! $class || ! class_exists($class)) {
                                        return;
                                    }

                                    $instance = JobInspector::make($class);
                                    $hasCron = $instance instanceof HasPredefinedCronExpression;

                                    if (! $hasCron) {
                                        $fail('A cron expression is required because the job class does not define one.');
                                    }
                                },
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->label('Description / Notes')
                            ->placeholder('Optional notes about what this job does, when it runs, or who owns it.')
                            ->rows(2)
                            ->columnSpanFull()
                            ->nullable(),

                        Forms\Components\Toggle::make('enabled')
                            ->label('Enabled')
                            ->default(true)
                            ->columnSpanFull()
                            ->helperText('Disabled jobs are removed from the external scheduler but kept in the database.'),
                    ])
                    ->columns(2),

                Grid::make(3)->schema([
                    Section::make('Constructor Arguments')
                        ->columnSpan(2)
                        ->schema(function (Get $get) {
                            $class = $get('class') ?? '';

                            if (! filled($class)) {
                                return [Forms\Components\Placeholder::make('_no_class')
                                    ->label('')
                                    ->content('No job has been selected.')
                                    ->extraAttributes(['class' => 'text-sm text-gray-400 italic'])];
                            }

                            $fields = JobFormBuilder::buildFields($class);

                            if (empty($fields)) {
                                return [Forms\Components\Placeholder::make('_no_args')
                                    ->label('')
                                    ->content("This job doesn't have any arguments.")
                                    ->extraAttributes(['class' => 'text-sm text-gray-400 italic'])];
                            }

                            return $fields;
                        })
                        ->columns(2),

                    Section::make('Retry Policy')
                        ->columnSpan(1)
                        ->description('Leave blank to use the config defaults.')
                        ->schema([
                            Forms\Components\TextInput::make('retry_maximum_event_age_seconds')
                                ->label('Max event age (seconds)')
                                ->numeric()
                                ->minValue(60)
                                ->maxValue(86400)
                                ->default(86400)
                                ->placeholder('86400')
                                ->helperText('Range: 60–86 400 (24 h).')
                                ->rules([
                                    fn () => function (string $attribute, mixed $value, \Closure $fail) {
                                        if ($value !== null && ($value < 60 || $value > 86400)) {
                                            $fail('Max event age must be between 60 and 86 400 seconds.');
                                        }
                                    },
                                ]),

                            Forms\Components\TextInput::make('retry_maximum_retry_attempts')
                                ->label('Max retry attempts')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(185)
                                ->default(185)
                                ->placeholder('185')
                                ->helperText('Range: 0–185.')
                                ->rules([
                                    fn () => function (string $attribute, mixed $value, \Closure $fail) {
                                        if ($value !== null && ($value < 0 || $value > 185)) {
                                            $fail('Max retry attempts must be between 0 and 185.');
                                        }
                                    },
                                ]),
                        ]),
                ]),
            ]);
    }

    // ── Table ─────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ColorColumn::make('_status_dot')
                    ->label('')
                    ->getStateUsing(fn (ScheduledJob $record) => $record->last_status?->color() ?? 'gray')
                    ->width('4px'),

                Tables\Columns\TextColumn::make('class')
                    ->label('Job')
                    ->formatStateUsing(fn (string $state) => self::resolveLabel($state))
                    ->description(fn (ScheduledJob $record) => $record->group)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('effective_cron')
                    ->label('Schedule')
                    ->getStateUsing(fn (ScheduledJob $record) => $record->isOnce() ? 'once' : $record->effective_cron)
                    ->tooltip(fn (ScheduledJob $record) => ($record->isOnce() || $record->effective_cron === null) ? null : CronTranslator::describe($record->effective_cron))
                    ->badge()
                    ->color(fn (ScheduledJob $record) => $record->isOnce() ? 'info' : 'gray'),

                Tables\Columns\ToggleColumn::make('enabled')
                    ->label('Enabled')
                    ->disabled(fn (ScheduledJob $record) => $record->isOnce())
                    ->afterStateUpdated(function (ScheduledJob $record, bool $state) {
                        try {
                            $state
                                ? TaskBridge::enable($record->class)
                                : TaskBridge::disable($record->class);
                        } catch (\Throwable) {
                            // Non-fatal: record is saved, sync can be re-run manually
                        }
                    }),

                Tables\Columns\TextColumn::make('last_run_at')
                    ->label('Last Run')
                    ->since()
                    ->placeholder('never')
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_status')
                    ->label('Status')
                    ->badge()
                    ->placeholder('—')
                    ->color(fn (?RunStatus $state) => $state?->color() ?? 'gray')
                    ->formatStateUsing(fn (?RunStatus $state) => $state?->label() ?? '—'),

                Tables\Columns\TextColumn::make('next_run')
                    ->label('Next Run')
                    ->getStateUsing(function (ScheduledJob $record) {
                        if ($record->isOnce()) {
                            return $record->run_once_at?->format('Y-m-d H:i') ?? '—';
                        }
                        try {
                            return CronTranslator::nextRunAt($record->effective_cron)->format('Y-m-d H:i');
                        } catch (\Throwable) {
                            return '—';
                        }
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('group')
                    ->options(fn () => ScheduledJob::whereNotNull('group')->distinct()->pluck('group', 'group')),

                Tables\Filters\SelectFilter::make('enabled')
                    ->options(['1' => 'Enabled', '0' => 'Disabled']),

                Tables\Filters\SelectFilter::make('last_status')
                    ->label('Last Status')
                    ->options(
                        collect(RunStatus::cases())
                            ->mapWithKeys(fn (RunStatus $case) => [$case->value => $case->label()])
                            ->toArray()
                    ),

                Tables\Filters\TernaryFilter::make('run_once_at')
                    ->label('Type')
                    ->placeholder('All')
                    ->trueLabel('One-time only')
                    ->falseLabel('Recurring only')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('run_once_at'),
                        false: fn ($query) => $query->whereNull('run_once_at'),
                        blank: fn ($query) => $query,
                    ),
            ])
            ->actions(self::buildRowActions())
            ->paginationPageOptions(TaskBridgePlugin::get()->getPaginationPageOptions())
            ->defaultPaginationPageOption(TaskBridgePlugin::get()->getDefaultPaginationPageOption())
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('enable')
                        ->label('Enable selected')
                        ->icon('heroicon-o-check-circle')
                        ->action(fn ($records) => $records->each(
                            fn (ScheduledJob $job) => TaskBridge::enable($job->class)
                        )),
                    Actions\BulkAction::make('disable')
                        ->label('Disable selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn ($records) => $records->each(
                            fn (ScheduledJob $job) => TaskBridge::disable($job->class)
                        )),
                    Actions\DeleteBulkAction::make()
                        ->after(function ($records) {
                            foreach ($records as $record) {
                                try {
                                    TaskBridge::getEventBridge()->remove($record->identifier);
                                } catch (\Throwable) {
                                }
                            }
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [RunsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScheduledJobs::route('/'),
            'create' => CreateScheduledJob::route('/create'),
            'edit' => EditScheduledJob::route('/{record}/edit'),
            'view' => ViewScheduledJob::route('/{record}'),
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Build grouped, alphabetically sorted options for the class Select.
     *
     * Groups come from GroupedJob::group(); default group is 'Other'.
     * Labels come from LabeledJob::taskLabel(); fallback is class_basename().
     *
     * When preventDuplicates is enabled, already-registered classes are
     * included but rendered with a greyed-out HTML label so the user can
     * see what is taken. Sorting is applied on the raw label before any
     * HTML is injected, ensuring alphabetical order is preserved.
     */
    public static function buildClassOptions(): array
    {
        $preventDuplicates = TaskBridgePlugin::get()->shouldPreventDuplicates();
        $registered = app(\CodeTechNL\TaskBridge\TaskBridge::class)->getRegisteredClasses();
        $existing = ScheduledJob::recurring()->pluck('class')->flip();

        // ── 1. Collect raw data (no HTML yet) ────────────────────────────────
        // Structure: [group => [class => ['label' => string, 'taken' => bool]]]
        $raw = [];

        foreach ($registered as $class) {
            if (! class_exists($class)) {
                continue;
            }

            // Skip jobs whose constructors require complex (non-scalar) arguments.
            // These cannot be serialized for a recurring EventBridge schedule without
            // hardcoded args, and the create form has no way to supply them.
            if (! JobInspector::hasSimpleConstructor($class)) {
                continue;
            }

            if (JobInspector::make($class) instanceof HidesFromTaskCreation) {
                continue;
            }

            $isTaken = isset($existing[$class]);

            // When preventDuplicates is off, skip the taken check entirely
            // so taken classes appear as fully selectable options.
            // When preventDuplicates is on, include them so they're visible
            // but will be rendered disabled.
            $label = self::resolveLabel($class);
            $group = self::resolveGroup($class) ?? 'Other';

            $raw[$group][$class] = ['label' => $label, 'taken' => $isTaken];
        }

        // ── 2. Sort groups alphabetically, entries within each group by label ─
        ksort($raw);
        foreach ($raw as &$entries) {
            uasort($entries, fn (array $a, array $b) => strcmp($a['label'], $b['label']));
        }
        unset($entries);

        // ── 3. Render final options (apply HTML for disabled state if needed) ─
        $grouped = [];

        foreach ($raw as $group => $entries) {
            foreach ($entries as $class => $data) {
                if ($data['taken'] && $preventDuplicates) {
                    // Visually disabled: greyed-out text + badge
                    $grouped[$group][$class] = sprintf(
                        '<span class="opacity-40 cursor-not-allowed">%s <span class="text-xs font-medium">(registered)</span></span>',
                        e($data['label'])
                    );
                } else {
                    $grouped[$group][$class] = $data['label'];
                }
            }
        }

        return $grouped;
    }

    /**
     * Build the table row actions, either inline or collapsed into a dropdown,
     * depending on the TaskBridgePlugin::groupActions() setting.
     */
    public static function buildRowActions(): array
    {
        $actions = [
            RunJobAction::make()
                ->hidden(fn (ScheduledJob $record) => $record->isOnce()),
            DryRunJobAction::make()
                ->hidden(fn (ScheduledJob $record) => $record->isOnce()),
            Actions\EditAction::make()
                ->hidden(fn (ScheduledJob $record) => $record->isOnce()),
            Actions\DeleteAction::make()
                ->after(function (ScheduledJob $record) {
                    try {
                        TaskBridge::getEventBridge()->remove($record->identifier);
                    } catch (\Throwable) {
                    }
                }),
            Actions\ViewAction::make(),
        ];

        if (TaskBridgePlugin::get()->shouldGroupActions()) {
            return [Actions\ActionGroup::make($actions)];
        }

        return $actions;
    }

    /**
     * Build options for the queue connection dropdown.
     * Only includes connections that use the SQS driver.
     */
    public static function buildQueueConnectionOptions(): array
    {
        $connections = config('queue.connections', []);
        $options = [];

        foreach ($connections as $name => $config) {
            if (($config['driver'] ?? '') === 'sqs') {
                $options[$name] = $name;
            }
        }

        return $options;
    }

    /**
     * Resolve a human-readable label for a class name.
     *
     * Priority: #[SchedulableJob(name:)] → HasCustomLabel::taskLabel() → sentence-case basename.
     * E.g. MyClassIsThis → "My class is this"
     */
    public static function resolveLabel(string $class): string
    {
        if (! class_exists($class)) {
            return class_basename($class).' ⚠';
        }

        $attr = JobInspector::getSchedulableJobAttribute($class);
        if ($attr?->name !== null) {
            return $attr->name;
        }

        $instance = JobInspector::make($class);

        if ($instance instanceof HasCustomLabel) {
            return $instance->taskLabel();
        }

        return ucfirst(mb_strtolower(Str::headline(class_basename($class))));
    }

    /**
     * Resolve a group for a class name.
     *
     * Priority: #[SchedulableJob(group:)] → HasGroup::group() → namespace segment before the class name.
     * Returns null when the class lives directly under a root segment (Jobs, etc.).
     *
     * E.g. App\Jobs\Reporting\SendReport → "Reporting"
     *      App\Jobs\SendReport            → null
     */
    public static function resolveGroup(string $class): ?string
    {
        if (! class_exists($class)) {
            return null;
        }

        $attr = JobInspector::getSchedulableJobAttribute($class);
        if ($attr?->group !== null) {
            return $attr->group;
        }

        $instance = JobInspector::make($class);

        if ($instance instanceof HasGroup) {
            return $instance->group();
        }

        $parts = explode('\\', $class);

        if (count($parts) < 3) {
            return null;
        }

        $parentSegment = $parts[count($parts) - 2];

        $rootSegments = ['Jobs', 'Commands', 'Console', 'App', 'Http', 'Listeners', 'Events'];

        if (in_array($parentSegment, $rootSegments, strict: true)) {
            return null;
        }

        return Str::headline($parentSegment);
    }
}
