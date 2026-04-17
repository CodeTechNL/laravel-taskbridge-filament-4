<?php

namespace CodeTechNL\TaskBridgeFilament\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Covers: required string, required nullable string, required int, required float, required bool,
 * optional string with default, optional nullable int with null default.
 */
class ScalarArgJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public readonly string $recipient,
        public readonly ?string $note,
        public readonly int $retries,
        public readonly float $threshold,
        public readonly bool $notify,
        public readonly string $mode = 'fast',
        public readonly ?int $limit = null,
    ) {}

    public function handle(): void {}
}
