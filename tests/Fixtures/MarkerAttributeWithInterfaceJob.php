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
 * Job carrying a bare #[SchedulableJob] marker (no attribute params) AND
 * implementing HasCustomLabel / HasGroup. Used to verify that a null attribute
 * value correctly falls back to the interface method.
 */
#[SchedulableJob]
class MarkerAttributeWithInterfaceJob implements HasCustomLabel, HasGroup, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function taskLabel(): string
    {
        return 'Interface Label (should be used)';
    }

    public function group(): string
    {
        return 'Interface Group (should be used)';
    }

    public function handle(): void {}
}
