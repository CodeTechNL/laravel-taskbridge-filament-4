<?php

namespace CodeTechNL\TaskBridgeFilament\Widgets;

use CodeTechNL\TaskBridge\Enums\RunStatus;
use CodeTechNL\TaskBridge\Models\ScheduledJob;
use CodeTechNL\TaskBridge\Models\ScheduledJobRun;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TaskBridgeWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $total = ScheduledJob::count();
        $active = ScheduledJob::where('enabled', true)->count();
        $disabled = ScheduledJob::where('enabled', false)->count();
        $failedLast24h = ScheduledJobRun::where('status', RunStatus::Failed)
            ->where('started_at', '>=', now()->subDay())
            ->count();

        return [
            Stat::make('Total Jobs', $total)
                ->icon('heroicon-o-clock')
                ->color('gray'),

            Stat::make('Active Jobs', $active)
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Disabled Jobs', $disabled)
                ->icon('heroicon-o-pause-circle')
                ->color('warning'),

            Stat::make('Failed (24h)', $failedLast24h)
                ->icon('heroicon-o-x-circle')
                ->color($failedLast24h > 0 ? 'danger' : 'success'),
        ];
    }
}
