<?php

namespace CodeTechNL\TaskBridgeFilament\Resources\ScheduledJobResource\Pages;

use CodeTechNL\TaskBridge\Facades\TaskBridge;
use CodeTechNL\TaskBridge\Support\JobInspector;
use CodeTechNL\TaskBridgeFilament\Resources\ScheduledJobResource;
use CodeTechNL\TaskBridgeFilament\Support\JobFormBuilder;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditScheduledJob extends EditRecord
{
    protected static string $resource = ScheduledJobResource::class;

    /**
     * Expand the stored positional constructor_arguments array back into
     * individual arg_* form fields so the edit form pre-fills correctly.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $class = $data['class'] ?? null;
        $stored = $data['constructor_arguments'] ?? [];

        if ($class && class_exists($class) && ! empty($stored)) {
            $params = JobInspector::getConstructorParameters($class);
            foreach ($params as $i => $param) {
                $data["arg_{$param->getName()}"] = $stored[$i] ?? null;
            }
        }

        unset($data['constructor_arguments']);

        return $data;
    }

    /**
     * Re-collect arg_* form fields into constructor_arguments before saving.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $class = $data['class'] ?? $this->record->class;

        $data['constructor_arguments'] = JobFormBuilder::resolveArguments($class, $data);
        foreach (array_keys($data) as $key) {
            if (str_starts_with($key, 'arg_')) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->after(function () {
                    try {
                        TaskBridge::getEventBridge()->remove($this->record->identifier);
                    } catch (\Throwable) {
                    }
                }),
            Actions\ViewAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $record = $this->record->fresh();

        try {
            if ($record->enabled) {
                TaskBridge::enable($record->class);
            } else {
                TaskBridge::disable($record->class);
            }
        } catch (\Throwable) {
            // Non-fatal: record is saved, sync can be re-run manually
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
