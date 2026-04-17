<?php

namespace CodeTechNL\TaskBridgeFilament\Tests\Fixtures;

use CodeTechNL\TaskBridge\Attributes\SchedulableJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Job with all three attribute parameters set. */
#[SchedulableJob(name: 'My Attribute Label', group: 'My Attribute Group', cron: '0 7 * * *')]
class AttributeLabelJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function handle(): void {}
}
