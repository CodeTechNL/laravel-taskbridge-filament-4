<?php

namespace CodeTechNL\TaskBridgeFilament\Tests\Fixtures;

use CodeTechNL\TaskBridge\Attributes\SchedulableJob;
use CodeTechNL\TaskBridge\Contracts\HasCustomLabel;
use CodeTechNL\TaskBridge\Contracts\HasGroup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Job carrying both a #[SchedulableJob] attribute AND implementing
 * HasCustomLabel / HasGroup. Used to verify that the attribute takes
 * precedence over the interface methods.
 */
#[SchedulableJob(name: 'Attribute Name Wins', group: 'Attribute Group Wins')]
class AttributeOverridesInterfaceJob implements HasCustomLabel, HasGroup, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function taskLabel(): string
    {
        return 'Interface Label (should be overridden)';
    }

    public function group(): string
    {
        return 'Interface Group (should be overridden)';
    }

    public function handle(): void {}
}
