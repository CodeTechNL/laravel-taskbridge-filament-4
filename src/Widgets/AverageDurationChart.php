<?php

namespace CodeTechNL\TaskBridgeFilament\Widgets;

use CodeTechNL\TaskBridge\Models\ScheduledJobRun;
use Filament\Widgets\ChartWidget;

class AverageDurationChart extends ChartWidget
{
    protected ?string $heading = 'Average Duration (14 days)';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $runModel = config('taskbridge.models.scheduled_job_run', ScheduledJobRun::class);

        $days = collect(range(13, 0))->map(fn (int $i) => now()->subDays($i)->toDateString());

        $data = $runModel::query()
            ->selectRaw('DATE(started_at) as date, AVG(duration_ms) as avg_ms')
            ->where('started_at', '>=', now()->subDays(13)->startOfDay())
            ->whereNotNull('duration_ms')
            ->groupBy('date')
            ->pluck('avg_ms', 'date');

        return [
            'datasets' => [
                [
                    'label' => 'Avg Duration (s)',
                    'data' => $days->map(fn ($d) => $data->has($d) ? round($data[$d] / 1000, 2) : null)->values()->toArray(),
                    'borderColor' => 'rgb(99, 102, 241)',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                    'spanGaps' => true,
                ],
            ],
            'labels' => $days->map(fn ($d) => now()->parse($d)->format('M j'))->values()->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => ['display' => true, 'text' => 'Seconds'],
                ],
            ],
        ];
    }
}
