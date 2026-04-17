<?php

namespace CodeTechNL\TaskBridgeFilament\Resources\ScheduledJobResource\Pages;

use CodeTechNL\TaskBridge\Contracts\HasPredefinedCronExpression;
use CodeTechNL\TaskBridge\Facades\TaskBridge;
use CodeTechNL\TaskBridge\Models\ScheduledJob;
use CodeTechNL\TaskBridge\Support\JobInspector;
use CodeTechNL\TaskBridgeFilament\Resources\ScheduledJobResource;
use CodeTechNL\TaskBridgeFilament\Support\JobFormBuilder;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Livewire\Attributes\On;

class CreateScheduledJob extends CreateRecord
{
    protected static string $resource = ScheduledJobResource::class;

    public function getTitle(): string
    {
        return 'Add scheduled job';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cancel')
                ->label('Cancel')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),

            Action::make('createAnother')
                ->label('Save & create another')
                ->color('gray')
                ->action('createAnother'),

            Action::make('save')
                ->label('Create')
                ->action('create'),
        ];
    }

    /**
     * Called when the JobPickerModal dispatches 'taskbridge-job-selected'.
     * Updates form state and triggers side-effects that mirror what the old
     * Select::afterStateUpdated() callback used to do.
     */
    #[On('taskbridge-job-selected')]
    public function onJobSelected(string $class): void
    {
        if (! class_exists($class)) {
            return;
        }

        $this->data['class'] = $class;
        $this->data['_identifier_hint'] = ScheduledJob::identifierFromClass($class);

        $attr = JobInspector::getSchedulableJobAttribute($class);
        $instance = JobInspector::make($class);

        $cron = $attr?->cron
            ?? ($instance instanceof HasPredefinedCronExpression ? $instance->cronExpression() : null);

        if ($cron !== null) {
            $this->data['cron_override'] = $cron;
        }

        $group = ScheduledJobResource::resolveGroup($class);
        if ($group !== null) {
            $this->data['group'] = $group;
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $class = $data['class'] ?? null;

        if (empty($class)) {
            Notification::make()
                ->title('No job selected')
                ->body('Please select a job before saving.')
                ->danger()
                ->send();

            $this->dispatch('taskbridge-picker-error');

            throw new Halt;
        }

        if (class_exists($class)) {
            $attr = JobInspector::getSchedulableJobAttribute($class);
            $instance = JobInspector::make($class);
            $data['identifier'] = ScheduledJob::identifierFromClass($class);

            // Store the class default separately from the user-provided override.
            // Priority: #[SchedulableJob(cron:)] → HasPredefinedCronExpression::cronExpression()
            $data['cron_expression'] = $attr?->cron
                ?? ($instance instanceof HasPredefinedCronExpression ? $instance->cronExpression() : null);

            // Prefer the group already set via the form (auto-detected or user-typed).
            // Fall back to resolveGroup() so the DB is always populated correctly.
            $data['group'] = $data['group'] ?? ScheduledJobResource::resolveGroup($class);
        }

        // Collect arg_* fields into a positional constructor_arguments array,
        // then remove them so they don't land in unknown DB columns.
        $data['constructor_arguments'] = JobFormBuilder::resolveArguments($class, $data);
        foreach (array_keys($data) as $key) {
            if (str_starts_with($key, 'arg_')) {
                unset($data[$key]);
            }
        }

        // Strip internal hint fields — they are dehydrated(false) but guard anyway
        unset($data['_identifier_hint'], $data['_default_cron_hint']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->record->enabled) {
            try {
                TaskBridge::enable($this->record->class);
            } catch (\Throwable) {
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
