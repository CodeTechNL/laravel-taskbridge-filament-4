<?php

namespace CodeTechNL\TaskBridgeFilament\Actions;

use Carbon\Carbon;
use CodeTechNL\TaskBridge\Facades\TaskBridge;
use CodeTechNL\TaskBridge\Support\JobInspector;
use CodeTechNL\TaskBridgeFilament\Resources\ScheduledJobResource;
use CodeTechNL\TaskBridgeFilament\Support\JobFormBuilder;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

class ScheduleJobOnceAction extends Action
{
    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'schedule_job_once')
            ->label('Schedule once')
            ->icon('heroicon-o-clock')
            ->color('info')
            ->modalHeading('Schedule a one-time run')
            ->modalDescription('Pick any registered job and a future datetime. EventBridge will fire it exactly once and self-delete the schedule afterwards.')
            ->form([
                Select::make('job_class')
                    ->label('Job')
                    ->options(fn () => self::buildJobOptions())
                    ->searchable()
                    ->required()
                    ->live()  // re-renders the form so constructor arg fields appear / disappear
                    ->helperText('Only jobs with simple (scalar) constructor parameters are listed.'),

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

                // Dynamically renders constructor argument fields for the selected job.
                // The section is hidden until a job with constructor parameters is selected.
                Section::make('Constructor Arguments')
                    ->description('These values will be passed to the job constructor when it fires.')
                    ->schema(fn (Get $get): array => JobFormBuilder::buildFields($get('job_class') ?? ''))
                    ->visible(fn (Get $get): bool => ! empty(JobFormBuilder::buildFields($get('job_class') ?? '')))
                    ->columns(1),
            ])
            ->action(function (array $data) {
                try {
                    $at = Carbon::createFromFormat(
                        'Y-m-d H:i',
                        "{$data['run_date']} {$data['run_hour']}:{$data['run_minute']}",
                        config('app.timezone'),
                    );

                    $arguments = JobFormBuilder::resolveArguments($data['job_class'], $data);
                    TaskBridge::scheduleOnce($data['job_class'], $at, $arguments);

                    $label = ScheduledJobResource::resolveLabel($data['job_class']);

                    Notification::make()
                        ->title('One-time run scheduled')
                        ->body("{$label} will run at {$at->format('Y-m-d H:i')}. Check its Run History for the pending entry.")
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Failed to schedule')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }

    /**
     * Build grouped options from all registered job classes.
     * Jobs with complex constructors (non-scalar params) are excluded.
     */
    private static function buildJobOptions(): array
    {
        $classes = app(\CodeTechNL\TaskBridge\TaskBridge::class)->getRegisteredClasses();

        $raw = [];

        foreach ($classes as $class) {
            if (! class_exists($class)) {
                continue;
            }

            // Skip jobs that require complex constructor arguments.
            if (! JobInspector::hasSimpleConstructor($class)) {
                continue;
            }

            $label = ScheduledJobResource::resolveLabel($class);
            $group = ScheduledJobResource::resolveGroup($class) ?? 'Other';

            $raw[$group][$class] = $label;
        }

        ksort($raw);

        foreach ($raw as &$entries) {
            asort($entries);
        }

        return $raw;
    }
}
