<?php

namespace CodeTechNL\TaskBridgeFilament\Resources\ScheduledJobResource\Pages;

use CodeTechNL\TaskBridgeFilament\Actions\ImportSchedulesAction;
use CodeTechNL\TaskBridgeFilament\Actions\ScheduleJobOnceAction;
use CodeTechNL\TaskBridgeFilament\Actions\SyncAction;
use CodeTechNL\TaskBridgeFilament\Actions\ValidateJobsAction;
use CodeTechNL\TaskBridgeFilament\Resources\ScheduledJobResource;
use CodeTechNL\TaskBridgeFilament\TaskBridgePlugin;
use Filament\Actions;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ListRecords;

class ListScheduledJobs extends ListRecords
{
    protected static string $resource = ScheduledJobResource::class;

    public function getTitle(): string
    {
        return TaskBridgePlugin::get()->getHeading();
    }

    public function getSubheading(): ?string
    {
        return TaskBridgePlugin::get()->getSubheading();
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                SyncAction::make(),
                ValidateJobsAction::make(),
                ImportSchedulesAction::make(),
            ])->label('Tools')->icon('heroicon-o-wrench-screwdriver')->color('gray')->button()->outlined(),
            ScheduleJobOnceAction::make(),
            Actions\CreateAction::make()->label('Add job'),
        ];
    }
}
