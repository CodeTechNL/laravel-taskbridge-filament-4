<?php

namespace CodeTechNL\TaskBridgeFilament\Resources;

use CodeTechNL\TaskBridge\Enums\RunStatus;
use CodeTechNL\TaskBridge\Enums\TriggeredBy;
use CodeTechNL\TaskBridge\Models\ScheduledJob;
use CodeTechNL\TaskBridge\Models\ScheduledJobRun;
use CodeTechNL\TaskBridgeFilament\Actions\ViewOutputAction;
use CodeTechNL\TaskBridgeFilament\Resources\ScheduledJobRunResource\Pages\ListScheduledJobRuns;
use CodeTechNL\TaskBridgeFilament\TaskBridgePlugin;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ScheduledJobRunResource extends Resource
{
    protected static ?string $model = ScheduledJobRun::class;

    public static function getModel(): string
    {
        return config('taskbridge.models.scheduled_job_run', ScheduledJobRun::class);
    }

    // ── Navigation ────────────────────────────────────────────────────────────

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return TaskBridgePlugin::get()->getNavigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return TaskBridgePlugin::get()->getRunLogNavigationLabel();
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return TaskBridgePlugin::get()->getRunLogNavigationIcon();
    }

    public static function getNavigationSort(): ?int
    {
        return TaskBridgePlugin::get()->getNavigationSort() + 1;
    }

    public static function getModelLabel(): string
    {
        return 'Run Log';
    }

    public static function getPluralModelLabel(): string
    {
        return TaskBridgePlugin::get()->getRunLogNavigationLabel();
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return TaskBridgePlugin::get()->getRunLogSlug();
    }

    // ── Form (required by Resource, not used) ─────────────────────────────────

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    // ── Table ─────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        $jobModel = config('taskbridge.models.scheduled_job', ScheduledJob::class);

        return $table
            ->defaultSort('started_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('scheduledJob.class')
                    ->label('Job')
                    ->formatStateUsing(fn (?string $state) => $state
                        ? ScheduledJobResource::resolveLabel($state)
                        : '—'
                    )
                    ->description(fn (ScheduledJobRun $record) => $record->scheduledJob?->group)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (RunStatus $state) => $state->color())
                    ->formatStateUsing(fn (RunStatus $state) => $state->label()),

                Tables\Columns\TextColumn::make('triggered_by')
                    ->label('Trigger')
                    ->badge()
                    ->color(fn (TriggeredBy $state) => $state->color())
                    ->formatStateUsing(fn (TriggeredBy $state) => $state->label()),

                Tables\Columns\TextColumn::make('started_at')
                    ->label('Started')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('duration_ms')
                    ->label('Duration')
                    ->formatStateUsing(function (?int $state) {
                        if ($state === null) {
                            return '—';
                        }
                        if ($state < 1000) {
                            return "{$state}ms";
                        }

                        return number_format($state / 1000, 2).'s';
                    }),

                Tables\Columns\TextColumn::make('jobs_dispatched')
                    ->label('Jobs Dispatched'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('scheduled_job_id')
                    ->label('Job')
                    ->options(fn () => $jobModel::orderBy('class')
                        ->get()
                        ->mapWithKeys(fn ($job) => [$job->id => ScheduledJobResource::resolveLabel($job->class)])
                        ->toArray()
                    )
                    ->searchable(),

                Tables\Filters\SelectFilter::make('identifier')
                    ->label('Identifier')
                    ->options(fn () => $jobModel::orderBy('identifier')
                        ->pluck('identifier', 'identifier')
                        ->toArray()
                    )
                    ->query(fn (Builder $query, array $data): Builder => $data['value']
                            ? $query->whereHas('scheduledJob', fn ($q) => $q->where('identifier', $data['value']))
                            : $query
                    )
                    ->searchable(),

                Tables\Filters\SelectFilter::make('status')
                    ->options(
                        collect(RunStatus::cases())
                            ->mapWithKeys(fn (RunStatus $case) => [$case->value => $case->label()])
                            ->toArray()
                    ),

                Tables\Filters\SelectFilter::make('triggered_by')
                    ->label('Trigger')
                    ->options(
                        collect(TriggeredBy::cases())
                            ->mapWithKeys(fn (TriggeredBy $case) => [$case->value => $case->label()])
                            ->toArray()
                    ),
            ])
            ->actions([
                ViewOutputAction::make(),
            ])
            ->paginationPageOptions(TaskBridgePlugin::get()->getRunLogPaginationPageOptions())
            ->defaultPaginationPageOption(TaskBridgePlugin::get()->getRunLogDefaultPaginationPageOption())
            ->bulkActions([]);
    }

    // ── Pages ─────────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => ListScheduledJobRuns::route('/'),
        ];
    }
}
