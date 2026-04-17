<?php

namespace CodeTechNL\TaskBridgeFilament\Livewire;

use CodeTechNL\TaskBridge\Contracts\HidesFromTaskCreation;
use CodeTechNL\TaskBridge\Support\JobDiscoverer;
use CodeTechNL\TaskBridge\Support\JobInspector;
use CodeTechNL\TaskBridge\TaskBridge;
use CodeTechNL\TaskBridgeFilament\Enums\JobPickerSize;
use CodeTechNL\TaskBridgeFilament\Resources\ScheduledJobResource;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class JobPickerModal extends Component
{
    public JobPickerSize $size = JobPickerSize::Medium;

    public string $search = '';

    public ?string $currentClass = null;

    public ?string $currentLabel = null;

    public bool $hasError = false;

    public function selectJob(string $class): void
    {
        $this->currentClass = $class;
        $this->currentLabel = ScheduledJobResource::resolveLabel($class);
        $this->hasError = false;
        $this->dispatch('taskbridge-job-selected', class: $class);
    }

    #[On('taskbridge-picker-error')]
    public function showError(): void
    {
        $this->hasError = true;
    }

    /**
     * Build the grouped list of all available jobs.
     *
     * Merges the registered classes (already filtered by hasSimpleConstructor)
     * with every class found by directory scanning WITHOUT the constructor filter,
     * so incompatible jobs appear in the picker with an explanatory warning.
     *
     * @return array<string, list<array{class: string, label: string, compatible: bool, incompatibleParams: string[]}>>
     */
    #[Computed]
    public function jobs(): array
    {
        $registered = app(TaskBridge::class)->getRegisteredClasses();

        $paths = config('taskbridge.auto_discovery.paths', []);
        $mode = config('taskbridge.auto_discovery.mode', 'interface');

        $allDiscovered = match ($mode) {
            'attribute' => JobDiscoverer::discoverAllByAttribute($paths),
            'interface' => JobDiscoverer::discoverAll($paths),
            default => [],
        };

        $allClasses = array_unique(array_merge($registered, $allDiscovered));

        $jobs = [];

        foreach ($allClasses as $class) {
            if (! class_exists($class)) {
                continue;
            }

            try {
                $instance = JobInspector::make($class);
            } catch (\Throwable) {
                continue;
            }

            if ($instance instanceof HidesFromTaskCreation) {
                continue;
            }

            $label = ScheduledJobResource::resolveLabel($class);
            $group = ScheduledJobResource::resolveGroup($class) ?? 'Other';

            if ($this->search !== '') {
                $q = mb_strtolower($this->search);
                if (
                    ! str_contains(mb_strtolower($label), $q)
                    && ! str_contains(mb_strtolower($group), $q)
                ) {
                    continue;
                }
            }

            $compatible = JobInspector::hasSimpleConstructor($class);

            $jobs[$group][] = [
                'class' => $class,
                'label' => $label,
                'compatible' => $compatible,
                'incompatibleParams' => $compatible ? [] : JobInspector::getIncompatibleConstructorParams($class),
            ];
        }

        ksort($jobs);

        foreach ($jobs as &$entries) {
            usort($entries, fn (array $a, array $b) => strcmp($a['label'], $b['label']));
        }

        return $jobs;
    }

    public function render(): View
    {
        return view('taskbridge-filament::livewire.job-picker-modal');
    }
}
