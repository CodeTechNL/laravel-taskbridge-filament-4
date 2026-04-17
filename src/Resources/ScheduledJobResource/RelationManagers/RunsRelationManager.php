<?php

namespace CodeTechNL\TaskBridgeFilament\Resources\ScheduledJobResource\RelationManagers;

use CodeTechNL\TaskBridge\Enums\RunStatus;
use CodeTechNL\TaskBridge\Enums\TriggeredBy;
use CodeTechNL\TaskBridgeFilament\Actions\ViewOutputAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RunsRelationManager extends RelationManager
{
    protected static string $relationship = 'runs';

    protected static ?string $title = 'Run History';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (RunStatus $state) => $state->color())
                    ->formatStateUsing(fn (RunStatus $state) => $state->label()),

                Tables\Columns\TextColumn::make('triggered_by')
                    ->label('Trigger')
                    ->badge()
                    ->color(fn (TriggeredBy $state) => $state->color())
                    ->formatStateUsing(fn (TriggeredBy $state) => $state->label()),

                Tables\Columns\TextColumn::make('scheduled_for')
                    ->label('Scheduled For')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('started_at')
                    ->label('Started')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('duration_ms')
                    ->label('Duration')
                    ->formatStateUsing(function (?int $state) {
                        if ($state === null) {
                            return '—';
                        }
                        if ($state < 1000) {
                            return "{$state}ms";
                        }

                        return number_format($state / 1000, 2).'s';
                    }),

                Tables\Columns\TextColumn::make('jobs_dispatched')
                    ->label('Jobs Dispatched'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(
                        collect(RunStatus::cases())
                            ->mapWithKeys(fn (RunStatus $case) => [$case->value => $case->label()])
                            ->toArray()
                    ),

                Tables\Filters\SelectFilter::make('triggered_by')
                    ->label('Trigger')
                    ->options(
                        collect(TriggeredBy::cases())
                            ->mapWithKeys(fn (TriggeredBy $case) => [$case->value => $case->label()])
                            ->toArray()
                    ),
            ])
            ->actions([
                ViewOutputAction::make(),
            ]);
    }
}
