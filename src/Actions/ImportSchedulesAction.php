<?php

namespace CodeTechNL\TaskBridgeFilament\Actions;

use CodeTechNL\TaskBridge\Commands\ImportSchedulesCommand;
use CodeTechNL\TaskBridge\Models\ScheduledJob;
use CodeTechNL\TaskBridge\Support\JobInspector;
use Cron\CronExpression;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class ImportSchedulesAction extends Action
{
    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'import-schedules')
            ->label('Import schedules')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Import predefined schedules')
            ->modalDescription('This will upsert all schedules defined in taskbridge.schedules into the database. Invalid entries will be skipped.')
            ->modalSubmitActionLabel('Import')
            ->action(function () {
                $schedules = config('taskbridge.schedules', []);

                if (empty($schedules)) {
                    Notification::make()
                        ->title('Nothing to import')
                        ->body('No schedules are defined in taskbridge.schedules.')
                        ->info()
                        ->send();

                    return;
                }

                $jobModel = config('taskbridge.models.scheduled_job', ScheduledJob::class);

                $imported = [];
                $failed = [];

                foreach ($schedules as $class => $value) {
                    $label = class_basename($class);

                    if (! is_array($value) || ! array_key_exists('cron', $value)) {
                        $failed[] = ['label' => $label, 'reason' => "Entry must be an array with a 'cron' key"];

                        continue;
                    }

                    [$cron, $arguments] = ImportSchedulesCommand::parseEntry($value);

                    if (! class_exists($class)) {
                        $failed[] = ['label' => $label, 'reason' => 'Class not found'];

                        continue;
                    }

                    if (! JobInspector::hasSimpleConstructor($class)) {
                        $incompatible = implode(', ', JobInspector::getIncompatibleConstructorParams($class));
                        $failed[] = ['label' => $label, 'reason' => 'Non-scalar constructor: '.$incompatible];

                        continue;
                    }

                    if (! self::isValidCron($cron)) {
                        $failed[] = ['label' => $label, 'reason' => 'Invalid cron expression: '.$cron];

                        continue;
                    }

                    $argError = ImportSchedulesCommand::validateArguments($class, $arguments);
                    if ($argError !== null) {
                        $failed[] = ['label' => $label, 'reason' => $argError];

                        continue;
                    }

                    $identifier = $jobModel::identifierFromClass($class);

                    $jobModel::updateOrCreate(
                        ['identifier' => $identifier],
                        [
                            'class' => $class,
                            'cron_expression' => $cron,
                            'constructor_arguments' => $arguments ?: null,
                        ]
                    );

                    $imported[] = $label;
                }

                if (empty($failed)) {
                    Notification::make()
                        ->title('Import completed')
                        ->body(count($imported).' schedule(s) imported successfully.')
                        ->success()
                        ->send();

                    return;
                }

                $lines = [];

                if ($imported) {
                    $lines[] = '<strong>Imported ('.count($imported).'):</strong>';
                    foreach ($imported as $label) {
                        $lines[] = '&nbsp;&nbsp;• '.e($label);
                    }
                }

                $lines[] = '<strong>Failed ('.count($failed).'):</strong>';
                foreach ($failed as ['label' => $label, 'reason' => $reason]) {
                    $lines[] = '&nbsp;&nbsp;• '.e($label).': '.e($reason);
                }

                Notification::make()
                    ->title('Import completed with errors')
                    ->body(new HtmlString(implode('<br>', $lines)))
                    ->warning()
                    ->persistent()
                    ->send();
            });
    }

    private static function isValidCron(mixed $cron): bool
    {
        if (! is_string($cron) || trim($cron) === '') {
            return false;
        }

        try {
            new CronExpression($cron);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }
}
