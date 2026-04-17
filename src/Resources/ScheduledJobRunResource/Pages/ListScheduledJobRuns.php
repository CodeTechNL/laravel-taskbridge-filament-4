<?php

namespace CodeTechNL\TaskBridgeFilament\Resources\ScheduledJobRunResource\Pages;

use CodeTechNL\TaskBridgeFilament\Resources\ScheduledJobRunResource;
use CodeTechNL\TaskBridgeFilament\TaskBridgePlugin;
use Filament\Resources\Pages\ListRecords;

class ListScheduledJobRuns extends ListRecords
{
    protected static string $resource = ScheduledJobRunResource::class;

    public function getTitle(): string
    {
        return TaskBridgePlugin::get()->getRunLogHeading();
    }
}
