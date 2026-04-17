<?php

namespace CodeTechNL\TaskBridgeFilament\Actions;

use CodeTechNL\TaskBridge\Models\ScheduledJobRun;
use Filament\Actions\Action;

class ViewOutputAction
{
    public static function make(): Action
    {
        return Action::make('view_output')
            ->label('Output')
            ->icon('heroicon-o-document-text')
            ->visible(fn (ScheduledJobRun $record) => ! empty($record->output))
            ->modalHeading('Job Output')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalContent(fn (ScheduledJobRun $record) => view(
                'taskbridge-filament::modals.output-detail',
                ['output' => $record->output]
            ));
    }
}
