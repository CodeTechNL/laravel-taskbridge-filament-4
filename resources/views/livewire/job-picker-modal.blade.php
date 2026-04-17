<div
    x-data="{ open: false }"
    x-on:keydown.escape.window="open = false"
>
    {{-- ── Trigger ──────────────────────────────────────────────────────────── --}}
    <div class="flex flex-col gap-1">
        <div class="flex items-center gap-3">
            <button
                type="button"
                x-on:click="open = true"
                style="{{ $this->hasError
                    ? 'color: rgb(220 38 38); background: rgb(254 242 242); outline: 2px solid rgb(239 68 68); outline-offset: 0;'
                    : 'color: rgb(55 65 81); background: white; box-shadow: 0 1px 2px rgba(0,0,0,.05); outline: 1px solid rgb(209 213 219);' }}"
                class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition focus:outline-none"
            >
                <x-heroicon-o-squares-2x2 class="h-4 w-4" />
                {{ $this->currentClass ? 'Change job' : 'Browse jobs' }}
            </button>

            @if ($this->currentClass)
                <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                    {{ $this->currentLabel }}
                </span>
            @else
                <span class="text-sm italic text-gray-400 dark:text-gray-500">No job selected</span>
            @endif
        </div>

        @if ($this->hasError)
            <p class="text-sm text-red-600 dark:text-red-400">Please select a job.</p>
        @endif
    </div>

    {{-- ── Modal ────────────────────────────────────────────────────────────── --}}
    <div
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        style="display: none;"
    >
        {{-- Backdrop --}}
        <div
            class="absolute inset-0 bg-black/50"
            x-on:click="open = false"
        ></div>

        {{-- Panel --}}
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative z-10 flex max-h-[80vh] w-full flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-gray-900"
            style="max-width: {{ $this->size->maxWidth() }};"
        >
            {{-- Header --}}
            <div class="flex flex-shrink-0 items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Choose a job</h2>
                <button
                    type="button"
                    x-on:click="open = false"
                    class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                >
                    <x-heroicon-o-x-mark class="h-5 w-5" />
                </button>
            </div>

            {{-- Search --}}
            <div class="flex-shrink-0 border-b border-gray-100 px-6 py-3 dark:border-gray-800">
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center" style="padding-left: 0.875rem;">
                        <x-heroicon-o-magnifying-glass class="h-4 w-4 text-gray-400" />
                    </div>
                    <input
                        wire:model.live.debounce.500ms="search"
                        type="text"
                        placeholder="Search jobs…"
                        class="block w-full rounded-lg border border-gray-300 bg-white py-2 pr-4 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder-gray-500"
                        style="padding-left: 2.5rem;"
                    />
                </div>
            </div>

            {{-- Job list: explicit max-height instead of flex-1 so scrolling is reliable --}}
            <div class="overflow-y-auto px-6 py-4" style="max-height: calc(80vh - 8rem);">
                {{-- Each group is full-width; items within a group are 2 columns --}}
                @forelse ($this->jobs as $group => $groupJobs)
                    <div style="margin-bottom: 1.5rem;">
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            {{ $group }}
                        </h3>

                        <div style="display: grid; grid-template-columns: repeat({{ $this->size->columns() }}, 1fr); gap: 0.5rem;">
                            @foreach ($groupJobs as $job)
                                @if ($job['compatible'])
                                    <button
                                        type="button"
                                        wire:click="selectJob('{{ addslashes($job['class']) }}')"
                                        x-on:click="open = false"
                                        @class([
                                            'group flex w-full items-start rounded-xl border px-4 py-3 text-left transition',
                                            'border-primary-300 bg-primary-50 ring-2 ring-primary-500 dark:border-primary-600 dark:bg-primary-900/30'
                                                => $this->currentClass === $job['class'],
                                            'border-gray-200 bg-white hover:border-primary-300 hover:bg-primary-50/50 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-700 dark:hover:bg-primary-900/10'
                                                => $this->currentClass !== $job['class'],
                                        ])
                                    >
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ $job['label'] }}
                                            </p>
                                            <p class="mt-0.5 truncate text-xs text-gray-400 dark:text-gray-500">
                                                {{ class_basename($job['class']) }}
                                            </p>
                                        </div>
                                    </button>
                                @else
                                    <div class="flex w-full items-start rounded-xl border border-red-200 bg-red-50 px-4 py-3 dark:border-red-900/40 dark:bg-red-900/10">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-1.5">
                                                <x-heroicon-o-exclamation-triangle class="h-3.5 w-3.5 flex-shrink-0 text-red-500" />
                                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                    {{ $job['label'] }}
                                                </p>
                                            </div>
                                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                                Cannot be scheduled — incompatible constructor
                                                {{ Str::plural('parameter', count($job['incompatibleParams'])) }}:
                                                {{ implode(', ', $job['incompatibleParams']) }}
                                            </p>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="py-12 text-center">
                        <x-heroicon-o-magnifying-glass class="mx-auto h-8 w-8 text-gray-300 dark:text-gray-600" />
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No jobs found</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
