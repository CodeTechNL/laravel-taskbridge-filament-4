<?php

namespace CodeTechNL\TaskBridgeFilament\Widgets;

use CodeTechNL\TaskBridge\Enums\RunStatus;
use CodeTechNL\TaskBridge\Models\ScheduledJobRun;
use Filament\Widgets\ChartWidget;

class RunHistoryChart extends ChartWidget
{
    protected ?string $heading = 'Run History (14 days)';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $runModel = config('taskbridge.models.scheduled_job_run', ScheduledJobRun::class);

        $days = collect(range(13, 0))->map(fn (int $i) => now()->subDays($i)->toDateString());

        $runs = $runModel::query()
            ->selectRaw('DATE(started_at) as date, status, COUNT(*) as count')
            ->where('started_at', '>=', now()->subDays(13)->startOfDay())
            ->groupBy('date', 'status')
            ->get()
            ->groupBy('date');

        $mapCount = fn (RunStatus $status) => $days
            ->map(fn ($d) => (int) ($runs->get($d)?->firstWhere('status', $status->value)?->count ?? 0))
            ->values()
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => RunStatus::Succeeded->label(),
                    'data' => $mapCount(RunStatus::Succeeded),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.7)',
                    'borderColor' => 'rgb(34, 197, 94)',
                ],
                [
                    'label' => RunStatus::Failed->label(),
                    'data' => $mapCount(RunStatus::Failed),
                    'backgroundColor' => 'rgba(239, 68, 68, 0.7)',
                    'borderColor' => 'rgb(239, 68, 68)',
                ],
                [
                    'label' => RunStatus::Skipped->label(),
                    'data' => $mapCount(RunStatus::Skipped),
                    'backgroundColor' => 'rgba(234, 179, 8, 0.7)',
                    'borderColor' => 'rgb(234, 179, 8)',
                ],
            ],
            'labels' => $days->map(fn ($d) => now()->parse($d)->format('M j'))->values()->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['stacked' => true],
                'y' => ['stacked' => true, 'beginAtZero' => true],
            ],
        ];
    }
}
