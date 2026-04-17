<div>
    @if ($missedJobs->isNotEmpty())
        <div class="fi-wi-stats-overview rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="p-4">
                <div class="flex items-start gap-3 rounded-lg bg-warning-50 p-4 ring-1 ring-warning-200 dark:bg-warning-500/10 dark:ring-warning-500/20">
                    <x-heroicon-o-exclamation-triangle class="mt-0.5 h-5 w-5 shrink-0 text-warning-500" />
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-warning-800 dark:text-warning-400">
                            {{ $missedJobs->count() }} {{ Str::plural('job', $missedJobs->count()) }} may have missed their schedule
                        </p>
                        <ul class="mt-2 space-y-1">
                            @foreach ($missedJobs as $job)
                                <li class="flex items-center gap-2 text-sm text-warning-700 dark:text-warning-300">
                                    <span class="font-medium">{{ class_basename($job->class) }}</span>
                                    @if ($job->group)
                                        <span class="text-warning-500 dark:text-warning-500">({{ $job->group }})</span>
                                    @endif
                                    <span class="text-warning-500 dark:text-warning-500">—</span>
                                    <span>
                                        {{ $job->last_run_at ? 'Last ran ' . $job->last_run_at->diffForHumans() : 'Never run' }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
