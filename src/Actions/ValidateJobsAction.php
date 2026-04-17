<?php

namespace CodeTechNL\TaskBridgeFilament\Actions;

use CodeTechNL\TaskBridge\Models\ScheduledJob;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\HtmlString;

class ValidateJobsAction extends Action
{
    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'validate')
            ->label('Validate')
            ->icon('heroicon-o-shield-check')
            ->color('gray')
            ->action(function (self $action) {
                $jobs = ScheduledJob::all();

                $missing = [];
                $notQueueable = [];
                $valid = [];

                foreach ($jobs as $job) {
                    if (! class_exists($job->class)) {
                        $missing[] = $job->class;

                        continue;
                    }

                    if (! is_a($job->class, ShouldQueue::class, true)) {
                        $notQueueable[] = $job->class;

                        continue;
                    }

                    $valid[] = $job->class;
                }

                if (empty($missing) && empty($notQueueable)) {
                    Notification::make()
                        ->title('All jobs are valid')
                        ->body(count($valid).' job(s) checked — no issues found.')
                        ->success()
                        ->send();

                    return;
                }

                $lines = [];

                if ($missing) {
                    $lines[] = '<strong>Class not found ('.count($missing).'):</strong>';
                    foreach ($missing as $class) {
                        $lines[] = '&nbsp;&nbsp;• '.e($class);
                    }
                }

                if ($notQueueable) {
                    $lines[] = '<strong>Does not implement ShouldQueue ('.count($notQueueable).'):</strong>';
                    foreach ($notQueueable as $class) {
                        $lines[] = '&nbsp;&nbsp;• '.e($class);
                    }
                }

                Notification::make()
                    ->title('Validation issues found')
                    ->body(new HtmlString(implode('<br>', $lines)))
                    ->danger()
                    ->persistent()
                    ->send();
            });
    }
}
