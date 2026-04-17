<?php

namespace CodeTechNL\TaskBridgeFilament\Widgets;

use CodeTechNL\TaskBridge\Enums\RunStatus;
use CodeTechNL\TaskBridge\Models\ScheduledJob;
use CodeTechNL\TaskBridge\Models\ScheduledJobRun;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class JobStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $jobModel = config('taskbridge.models.scheduled_job', ScheduledJob::class);
        $runModel = config('taskbridge.models.scheduled_job_run', ScheduledJobRun::class);

        $totalJobs = $jobModel::count();
        $enabledJobs = $jobModel::where('enabled', true)->count();
        $runsToday = $runModel::whereDate('started_at', today())->count();

        $since7Days = now()->subDays(7);
        $recentRuns = $runModel::where('started_at', '>=', $since7Days)->get();
        $totalRecent = $recentRuns->count();
        $succeededRecent = $recentRuns->where('status', RunStatus::Succeeded)->count();
        $successRate = $totalRecent > 0 ? (int) round(($succeededRecent / $totalRecent) * 100) : 0;

        $failures24h = $runModel::where('status', RunStatus::Failed)
            ->where('started_at', '>=', now()->subHours(24))
            ->count();

        $avgDurationMs = $runModel::where('started_at', '>=', $since7Days)
            ->whereNotNull('duration_ms')
            ->avg('duration_ms');

        $neverRun = $jobModel::whereNull('last_run_at')->count();

        return [
            Stat::make('Total Jobs', $totalJobs)
                ->description("{$enabledJobs} enabled")
                ->color('gray'),

            Stat::make('Runs Today', $runsToday)
                ->description('Across all jobs')
                ->color('primary'),

            Stat::make('Success Rate (7d)', $successRate.'%')
                ->description("{$succeededRecent} of {$totalRecent} runs")
                ->color($successRate >= 90 ? 'success' : ($successRate >= 70 ? 'warning' : 'danger')),

            Stat::make('Failures (24h)', $failures24h)
                ->color($failures24h > 0 ? 'danger' : 'success'),

            Stat::make('Avg Duration (7d)', $avgDurationMs ? number_format($avgDurationMs / 1000, 2).'s' : '—')
                ->description('Wall-clock time per run')
                ->color('gray'),

            Stat::make('Never Run', $neverRun)
                ->description('Jobs with no run history')
                ->color($neverRun > 0 ? 'warning' : 'success'),
        ];
    }
}
