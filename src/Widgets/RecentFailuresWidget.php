<?php

namespace CodeTechNL\TaskBridgeFilament\Widgets;

use CodeTechNL\TaskBridge\Enums\RunStatus;
use CodeTechNL\TaskBridge\Enums\TriggeredBy;
use CodeTechNL\TaskBridge\Models\ScheduledJobRun;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentFailuresWidget extends BaseWidget
{
    protected static ?string $heading = 'Recent Failures';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $runModel = config('taskbridge.models.scheduled_job_run', ScheduledJobRun::class);

        return $table
            ->query(
                $runModel::query()
                    ->with('scheduledJob')
                    ->where('status', RunStatus::Failed)
                    ->latest('started_at')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('scheduledJob.class')
                    ->label('Job')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—'),

                Tables\Columns\TextColumn::make('triggered_by')
                    ->label('Triggered By')
                    ->badge()
                    ->formatStateUsing(fn (TriggeredBy $state) => $state->label())
                    ->color(fn (TriggeredBy $state) => $state->color()),

                Tables\Columns\TextColumn::make('started_at')
                    ->label('Started')
                    ->since()
                    ->sortable(),

                Tables\Columns\TextColumn::make('duration_ms')
                    ->label('Duration')
                    ->formatStateUsing(fn (?int $state) => $state ? number_format($state / 1000, 2).'s' : '—'),

                Tables\Columns\TextColumn::make('output')
                    ->label('Error')
                    ->formatStateUsing(fn (mixed $state) => is_array($state) ? ($state['message'] ?? '—') : '—')
                    ->limit(80)
                    ->tooltip(fn (mixed $state) => is_array($state) ? ($state['message'] ?? null) : null),
            ])
            ->paginated(false)
            ->emptyStateHeading('No recent failures')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->emptyStateDescription('All jobs are running successfully.');
    }
}
