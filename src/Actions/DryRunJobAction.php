<?php

namespace CodeTechNL\TaskBridgeFilament\Actions;

use CodeTechNL\TaskBridge\Facades\TaskBridge;
use CodeTechNL\TaskBridge\Models\ScheduledJob;
use CodeTechNL\TaskBridgeFilament\Support\JobFormBuilder;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;

class DryRunJobAction extends Action
{
    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'dry-run')
            ->label('Dry run')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->modalHeading('Dry run job')
            ->modalDescription(fn (ScheduledJob $record): string => empty(JobFormBuilder::buildFields($record->class))
                ? 'This simulates the job without actually dispatching anything. Continue?'
                : 'Fill in the constructor arguments to simulate the job without dispatching anything.'
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
                    $run = TaskBridge::run($record->class, dryRun: true, force: true, arguments: $arguments);

                    Notification::make()
                        ->title('Dry run complete')
                        ->body('Status: '.$run->status->label().' | Would dispatch: '.$run->jobs_dispatched)
                        ->info()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Dry run failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
