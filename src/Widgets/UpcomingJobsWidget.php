<?php

namespace CodeTechNL\TaskBridgeFilament\Widgets;

use CodeTechNL\TaskBridge\Enums\RunStatus;
use CodeTechNL\TaskBridge\Models\ScheduledJob;
use CodeTechNL\TaskBridge\Support\CronTranslator;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UpcomingJobsWidget extends BaseWidget
{
    protected static ?string $heading = 'Scheduled Jobs';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $jobModel = config('taskbridge.models.scheduled_job', ScheduledJob::class);

        return $table
            ->query(
                $jobModel::query()
                    ->where('enabled', true)
                    ->where(fn ($q) => $q
                        ->whereNotNull('cron_override')
                        ->orWhereNotNull('cron_expression')
                    )
                    ->orderBy('class')
            )
            ->columns([
                Tables\Columns\TextColumn::make('class')
                    ->label('Job')
                    ->formatStateUsing(fn (string $state) => class_basename($state)),

                Tables\Columns\TextColumn::make('group')
                    ->label('Group')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('effective_cron')
                    ->label('Schedule')
                    ->formatStateUsing(fn (?string $state) => $state ? CronTranslator::describe($state) : '—')
                    ->tooltip(fn (?string $state) => $state),

                Tables\Columns\TextColumn::make('next_run_at')
                    ->label('Next Run')
                    ->state(function (ScheduledJob $record): string {
                        $cron = $record->effective_cron;
                        if (! $cron || ! CronTranslator::isValid($cron)) {
                            return '—';
                        }

                        return CronTranslator::nextRunAt($cron)->format('Y-m-d H:i');
                    }),

                Tables\Columns\TextColumn::make('last_run_at')
                    ->label('Last Run')
                    ->since()
                    ->placeholder('Never'),

                Tables\Columns\TextColumn::make('last_status')
                    ->label('Last Status')
                    ->badge()
                    ->formatStateUsing(fn (?RunStatus $state) => $state?->label() ?? 'Never run')
                    ->color(fn (?RunStatus $state) => $state?->color() ?? 'gray'),
            ])
            ->paginated(false)
            ->emptyStateHeading('No enabled jobs with a schedule')
            ->emptyStateIcon('heroicon-o-calendar');
    }
}
