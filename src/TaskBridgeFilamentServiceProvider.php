<?php

namespace CodeTechNL\TaskBridgeFilament;

use CodeTechNL\TaskBridgeFilament\Livewire\JobPickerModal;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class TaskBridgeFilamentServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'taskbridge-filament');

        Livewire::component('taskbridge-job-picker', JobPickerModal::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/taskbridge-filament'),
            ], 'taskbridge-filament-views');
        }
    }
}
