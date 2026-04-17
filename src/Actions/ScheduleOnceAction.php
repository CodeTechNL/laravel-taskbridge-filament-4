<?php

namespace CodeTechNL\TaskBridgeFilament\Actions;

use Carbon\Carbon;
use CodeTechNL\TaskBridge\Facades\TaskBridge;
use CodeTechNL\TaskBridge\Models\ScheduledJob;
use CodeTechNL\TaskBridgeFilament\Support\JobFormBuilder;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

class ScheduleOnceAction extends Action
{
    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'schedule_once')
            ->label('Schedule once')
            ->icon('heroicon-o-clock')
            ->color('info')
            ->modalHeading('Schedule one-time run')
            ->modalDescription('The job will be dispatched exactly once at the chosen time via EventBridge. The schedule self-destructs after it fires.')
            ->form(fn (ScheduledJob $record): array => array_filter([
                DatePicker::make('run_date')
                    ->label('Date')
                    ->required()
                    ->minDate(today()),

                Grid::make(2)->schema([
                    Select::make('run_hour')
                        ->label('Hour')
                        ->options(array_combine(
                            array_map(fn (int $h) => str_pad($h, 2, '0', STR_PAD_LEFT), range(0, 23)),
                            array_map(fn (int $h) => str_pad($h, 2, '0', STR_PAD_LEFT), range(0, 23)),
                        ))
                        ->default('09')
                        ->required(),

                    Select::make('run_minute')
                        ->label('Minute')
                        ->options(array_combine(
                            array_map(fn (int $m) => str_pad($m, 2, '0', STR_PAD_LEFT), range(0, 59)),
                            array_map(fn (int $m) => str_pad($m, 2, '0', STR_PAD_LEFT), range(0, 59)),
                        ))
                        ->default('00')
                        ->required(),
                ]),

                // Only rendered when the job has constructor parameters.
                ! empty(JobFormBuilder::buildFields($record->class))
                    ? Section::make('Constructor Arguments')
                        ->description('These values will be passed to the job constructor when it fires.')
                        ->schema(JobFormBuilder::buildFields($record->class))
                        ->columns(1)
                    : null,
            ]))
            ->action(function (ScheduledJob $record, array $data) {
                try {
                    $at = Carbon::createFromFormat(
                        'Y-m-d H:i',
                        "{$data['run_date']} {$data['run_hour']}:{$data['run_minute']}",
                        config('app.timezone'),
                    );

                    if (! $at->isFuture()) {
                        Notification::make()
                            ->title('Invalid date/time')
                            ->body('The scheduled time must be in the future.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $arguments = JobFormBuilder::resolveArguments($record->class, $data);
                    TaskBridge::scheduleOnce($record->class, $at, $arguments);

                    Notification::make()
                        ->title('One-time run scheduled')
                        ->body("Will run at {$at->format('Y-m-d H:i')}. Check Run History for the pending entry.")
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Failed to schedule')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
