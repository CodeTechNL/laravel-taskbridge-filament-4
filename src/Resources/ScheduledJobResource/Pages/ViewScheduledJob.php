<?php

namespace CodeTechNL\TaskBridgeFilament\Resources\ScheduledJobResource\Pages;

use CodeTechNL\TaskBridge\Enums\RunStatus;
use CodeTechNL\TaskBridge\Facades\TaskBridge;
use CodeTechNL\TaskBridge\Models\ScheduledJob;
use CodeTechNL\TaskBridge\Support\CronTranslator;
use CodeTechNL\TaskBridgeFilament\Resources\ScheduledJobResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewScheduledJob extends ViewRecord
{
    protected static string $resource = ScheduledJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('activate')
                ->label('Activate')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn () => ! $this->record->enabled && ! $this->record->isOnce())
                ->action(function () {
                    TaskBridge::enable($this->record->class);
                    $this->record = $this->record->fresh();
                    Notification::make()->title('Job activated')->success()->send();
                }),

            Action::make('deactivate')
                ->label('Deactivate')
                ->icon('heroicon-o-pause')
                ->color('warning')
                ->visible(fn () => $this->record->enabled && ! $this->record->isOnce())
                ->requiresConfirmation()
                ->action(function () {
                    TaskBridge::disable($this->record->class);
                    $this->record = $this->record->fresh();
                    Notification::make()->title('Job deactivated')->warning()->send();
                }),

            Actions\EditAction::make()
                ->hidden(fn () => $this->record->isOnce()),

            Actions\DeleteAction::make()
                ->after(function () {
                    try {
                        TaskBridge::getEventBridge()->remove($this->record->identifier);
                    } catch (\Throwable) {
                    }
                }),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->schema([
                Section::make('Job')
                    ->columnSpan(1)
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('class')->label('Class'),
                            IconEntry::make('_class_exists')
                                ->label('Class exists')
                                ->getStateUsing(fn (ScheduledJob $record) => class_exists($record->class))
                                ->boolean(),
                        ]),
                        TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        Grid::make(2)->schema([
                            TextEntry::make('identifier')->label('Identifier'),
                            TextEntry::make('group')->label('Group')->placeholder('—'),
                        ]),
                    ]),

                Section::make('Schedule')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('_type')
                            ->label('Type')
                            ->getStateUsing(fn (ScheduledJob $record) => $record->isOnce() ? 'One-time' : 'Recurring')
                            ->badge()
                            ->color(fn (ScheduledJob $record) => $record->isOnce() ? 'info' : 'gray'),
                        TextEntry::make('cron_override')
                            ->label('Cron')
                            ->placeholder('—')
                            ->hidden(fn (ScheduledJob $record) => $record->isOnce()),
                        TextEntry::make('_cron_description')
                            ->label('Description')
                            ->getStateUsing(fn (ScheduledJob $record) => $record->effective_cron
                                ? CronTranslator::describe($record->effective_cron)
                                : null)
                            ->placeholder('—')
                            ->hidden(fn (ScheduledJob $record) => $record->isOnce()),
                        TextEntry::make('run_once_at')
                            ->label('Scheduled for')
                            ->dateTime()
                            ->placeholder('—')
                            ->hidden(fn (ScheduledJob $record) => ! $record->isOnce()),
                        TextEntry::make('run_once_schedule_name')
                            ->label('Schedule name')
                            ->placeholder('—')
                            ->hidden(fn (ScheduledJob $record) => ! $record->isOnce()),
                    ]),

                Section::make('Status')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('enabled')
                            ->label('Enabled')
                            ->formatStateUsing(fn (bool $state) => $state ? 'Yes' : 'No')
                            ->badge()
                            ->color(fn (bool $state) => $state ? 'success' : 'danger'),
                        TextEntry::make('last_status')
                            ->label('Last Status')
                            ->badge()
                            ->placeholder('—')
                            ->color(fn (?RunStatus $state) => $state?->color() ?? 'gray')
                            ->formatStateUsing(fn (?RunStatus $state) => $state?->label() ?? '—'),
                        TextEntry::make('last_run_at')
                            ->label('Last Run')
                            ->since()
                            ->placeholder('never'),
                    ]),
            ]);
    }
}
