<?php

namespace CodeTechNL\TaskBridgeFilament\Widgets;

use CodeTechNL\TaskBridge\Models\ScheduledJob;
use CodeTechNL\TaskBridge\Support\CronTranslator;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class MissedJobsAlert extends Widget
{
    protected string $view = 'taskbridge-filament::widgets.missed-jobs-alert';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'missedJobs' => $this->getMissedJobs(),
        ];
    }

    private function getMissedJobs(): Collection
    {
        $jobModel = config('taskbridge.models.scheduled_job', ScheduledJob::class);

        return $jobModel::query()
            ->where('enabled', true)
            ->get()
            ->filter(function (ScheduledJob $job): bool {
                $cron = $job->effective_cron;

                if (! $cron || ! CronTranslator::isValid($cron)) {
                    return false;
                }

                try {
                    $previousRun = CronTranslator::previousRunAt($cron);
                    $nextRun = CronTranslator::nextRunAt($cron);
                    $intervalSeconds = $nextRun->getTimestamp() - $previousRun->getTimestamp();
                    $threshold = $previousRun->getTimestamp() - $intervalSeconds;
                    $lastRun = $job->last_run_at?->getTimestamp();

                    return $lastRun === null || $lastRun < $threshold;
                } catch (\Throwable) {
                    return false;
                }
            })
            ->values();
    }
}
