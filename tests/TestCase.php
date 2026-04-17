<?php

namespace CodeTechNL\TaskBridgeFilament\Tests;

use CodeTechNL\TaskBridge\TaskBridgeServiceProvider;
use CodeTechNL\TaskBridgeFilament\TaskBridgeFilamentServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            TaskBridgeServiceProvider::class,
            TaskBridgeFilamentServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
    }
}
