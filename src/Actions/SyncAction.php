<?php

namespace CodeTechNL\TaskBridgeFilament\Actions;

use CodeTechNL\TaskBridge\Facades\TaskBridge;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class SyncAction extends Action
{
    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'sync')
            ->label('Sync')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->action(function () {
                try {
                    $result = TaskBridge::sync();

                    Notification::make()
                        ->title('Sync completed')
                        ->body(sprintf(
                            'Created: %d · Updated: %d · Removed: %d',
                            $result->created,
                            $result->updated,
                            $result->removed,
                        ))
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Sync failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }
}
