<?php

namespace CodeTechNL\TaskBridgeFilament\Pages;

use BackedEnum;
use CodeTechNL\TaskBridgeFilament\TaskBridgePlugin;
use CodeTechNL\TaskBridgeFilament\Widgets\AverageDurationChart;
use CodeTechNL\TaskBridgeFilament\Widgets\JobStatsOverview;
use CodeTechNL\TaskBridgeFilament\Widgets\MissedJobsAlert;
use CodeTechNL\TaskBridgeFilament\Widgets\RecentFailuresWidget;
use CodeTechNL\TaskBridgeFilament\Widgets\RunHistoryChart;
use CodeTechNL\TaskBridgeFilament\Widgets\UpcomingJobsWidget;
use Filament\Pages\Dashboard;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class TaskBridgeDashboard extends Dashboard
{
    protected static string $routePath = 'taskbridge';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return TaskBridgePlugin::get()->getDashboardNavigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return TaskBridgePlugin::get()->getDashboardNavigationLabel();
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return TaskBridgePlugin::get()->getDashboardNavigationIcon();
    }

    public static function getNavigationSort(): ?int
    {
        return TaskBridgePlugin::get()->getDashboardNavigationSort();
    }

    public function getTitle(): string|Htmlable
    {
        return TaskBridgePlugin::get()->getDashboardTitle();
    }

    public function getWidgets(): array
    {
        return [
            MissedJobsAlert::class,
            JobStatsOverview::class,
            RunHistoryChart::class,
            AverageDurationChart::class,
            RecentFailuresWidget::class,
            UpcomingJobsWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 2;
    }
}
