<?php

namespace CodeTechNL\TaskBridgeFilament\Actions;

use CodeTechNL\TaskBridge\Facades\TaskBridge;
use CodeTechNL\TaskBridge\Models\ScheduledJob;
use CodeTechNL\TaskBridgeFilament\Support\JobFormBuilder;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;

class RunJobAction extends Action
{
    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'run')
            ->label('Run now')
            ->icon('heroicon-o-play')
            ->color('success')
            ->modalHeading('Run job now')
            ->modalDescription(fn (ScheduledJob $record): string => empty(JobFormBuilder::buildFields($record->class))
                ? 'This will immediately execute the job. Continue?'
                : 'Fill in the constructor arguments and run the job.'
            )
            ->form(fn (ScheduledJob $record): array => [
                Section::make('Constructor Arguments')
                    ->schema(! empty(JobFormBuilder::buildFields($record->class))
                        ? JobFormBuilder::buildFields($record->class)
                        : [Placeholder::make('_no_args')->label('')->content('No parameters.')->extraAttributes(['class' => 'text-sm text-gray-400 italic'])]),
            ])
            ->action(function (ScheduledJob $record, array $data) {
                try {
                    $arguments = JobFormBuilder::resolveArguments($record->class, $data);
                    $run = TaskBridge::run($record->class, force: true, arguments: $arguments);

                    Notification::make()
                        ->title('Job dispatched: '.$run->status->label())
                        ->body("Duration: {$run->duration_ms}ms | Dispatched: {$run->jobs_dispatched}")
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Job failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
