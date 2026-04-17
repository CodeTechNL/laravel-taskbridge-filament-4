<?php

use CodeTechNL\TaskBridgeFilament\Support\JobFormBuilder;
use CodeTechNL\TaskBridgeFilament\Tests\Fixtures\NoArgJob;
use CodeTechNL\TaskBridgeFilament\Tests\Fixtures\ScalarArgJob;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

describe('JobFormBuilder', function () {

    // ── buildFields() ──────────────────────────────────────────────────────────

    describe('buildFields()', function () {
        it('returns empty array for a job with no constructor arguments', function () {
            expect(JobFormBuilder::buildFields(NoArgJob::class))->toBe([]);
        });

        it('returns empty array for a non-existent class', function () {
            expect(JobFormBuilder::buildFields('NonExistentClass'))->toBe([]);
        });

        it('returns one field per constructor parameter', function () {
            $fields = JobFormBuilder::buildFields(ScalarArgJob::class);

            expect($fields)->toHaveCount(7);
        });

        it('uses arg_{name} as the field name', function () {
            $fields = JobFormBuilder::buildFields(ScalarArgJob::class);

            expect($fields[0]->getName())->toBe('arg_recipient');
            expect($fields[1]->getName())->toBe('arg_note');
            expect($fields[2]->getName())->toBe('arg_retries');
        });

        it('renders bool params as Select fields', function () {
            $fields = JobFormBuilder::buildFields(ScalarArgJob::class);
            // $notify is index 4
            expect($fields[4])->toBeInstanceOf(Select::class);
        });

        it('renders int params as numeric integer TextInput', function () {
            $fields = JobFormBuilder::buildFields(ScalarArgJob::class);
            // $retries is index 2
            expect($fields[2])->toBeInstanceOf(TextInput::class);
        });

        it('renders float params as numeric TextInput', function () {
            $fields = JobFormBuilder::buildFields(ScalarArgJob::class);
            // $threshold is index 3
            expect($fields[3])->toBeInstanceOf(TextInput::class);
        });

        it('renders string params as TextInput', function () {
            $fields = JobFormBuilder::buildFields(ScalarArgJob::class);
            // $recipient is index 0
            expect($fields[0])->toBeInstanceOf(TextInput::class);
        });

        it('marks required non-nullable params as required', function () {
            $fields = JobFormBuilder::buildFields(ScalarArgJob::class);
            // $recipient — required, non-nullable
            expect($fields[0]->isRequired())->toBeTrue();
        });

        it('marks required nullable params as required (Bug 1 fix)', function () {
            $fields = JobFormBuilder::buildFields(ScalarArgJob::class);
            // $note — required, nullable (?string $note)
            expect($fields[1]->isRequired())->toBeTrue();
        });

        it('does not mark optional params as required', function () {
            $fields = JobFormBuilder::buildFields(ScalarArgJob::class);
            // $mode — optional with default 'fast'
            expect($fields[5]->isRequired())->toBeFalse();
            // $limit — optional with null default
            expect($fields[6]->isRequired())->toBeFalse();
        });

        it('adds placeholder to optional nullable params whose default is null', function () {
            $fields = JobFormBuilder::buildFields(ScalarArgJob::class);
            // $limit — ?int $limit = null
            // In Filament v4, helperText() no longer has a getter — verify through placeholder instead.
            expect($fields[6]->getPlaceholder())->toContain('application default');
        });
    });

    // ── resolveArguments() ────────────────────────────────────────────────────

    describe('resolveArguments()', function () {
        it('returns empty array for a job with no constructor', function () {
            expect(JobFormBuilder::resolveArguments(NoArgJob::class, []))->toBe([]);
        });

        it('returns empty array for a non-existent class', function () {
            expect(JobFormBuilder::resolveArguments('NonExistentClass', []))->toBe([]);
        });

        it('resolves all scalar types from form data', function () {
            $data = [
                'arg_recipient' => 'hello@example.com',
                'arg_note' => 'some note',
                'arg_retries' => '3',
                'arg_threshold' => '0.75',
                'arg_notify' => '1',
                'arg_mode' => 'slow',
                'arg_limit' => '100',
            ];

            $args = JobFormBuilder::resolveArguments(ScalarArgJob::class, $data);

            expect($args[0])->toBe('hello@example.com')  // string
                ->and($args[1])->toBe('some note')        // ?string
                ->and($args[2])->toBe(3)                  // int
                ->and($args[3])->toBe(0.75)               // float
                ->and($args[4])->toBe(true)               // bool
                ->and($args[5])->toBe('slow')             // optional string
                ->and($args[6])->toBe(100);               // optional int
        });

        it('casts bool "0" to false', function () {
            $data = [
                'arg_recipient' => 'a@b.com',
                'arg_note' => null,
                'arg_retries' => '1',
                'arg_threshold' => '1.0',
                'arg_notify' => '0',
            ];

            $args = JobFormBuilder::resolveArguments(ScalarArgJob::class, $data);

            expect($args[4])->toBe(false);
        });

        it('passes null for a required nullable param submitted as empty string', function () {
            $data = [
                'arg_recipient' => 'a@b.com',
                'arg_note' => '',          // empty string → null
                'arg_retries' => '1',
                'arg_threshold' => '1.0',
                'arg_notify' => '1',
            ];

            $args = JobFormBuilder::resolveArguments(ScalarArgJob::class, $data);

            expect($args[1])->toBeNull();
        });

        it('passes null for a required nullable param when key is absent from data (Bug 2 fix)', function () {
            $data = [
                'arg_recipient' => 'a@b.com',
                // arg_note intentionally absent
                'arg_retries' => '1',
                'arg_threshold' => '1.0',
                'arg_notify' => '1',
            ];

            $args = JobFormBuilder::resolveArguments(ScalarArgJob::class, $data);

            // Array still has all 7 params; optional ones fall back to defaults.
            // The key assertion: required nullable $note at index 1 must be null, not missing.
            expect($args)->toHaveCount(7)
                ->and($args[1])->toBeNull();
        });

        it('falls back to the declared default for an optional param not in data', function () {
            $data = [
                'arg_recipient' => 'a@b.com',
                'arg_note' => null,
                'arg_retries' => '2',
                'arg_threshold' => '0.5',
                'arg_notify' => '1',
                // arg_mode and arg_limit absent — should use defaults
            ];

            $args = JobFormBuilder::resolveArguments(ScalarArgJob::class, $data);

            expect($args[5])->toBe('fast')   // default for $mode
                ->and($args[6])->toBeNull(); // default for $limit
        });

        it('passes null for an optional nullable param submitted as empty string', function () {
            $data = [
                'arg_recipient' => 'a@b.com',
                'arg_note' => null,
                'arg_retries' => '2',
                'arg_threshold' => '0.5',
                'arg_notify' => '1',
                'arg_mode' => 'fast',
                'arg_limit' => '',       // empty string → null
            ];

            $args = JobFormBuilder::resolveArguments(ScalarArgJob::class, $data);

            expect($args[6])->toBeNull();
        });

        it('produces a positional array that can be splatted to construct the job', function () {
            $data = [
                'arg_recipient' => 'test@example.com',
                'arg_note' => null,
                'arg_retries' => '5',
                'arg_threshold' => '0.9',
                'arg_notify' => '1',
                'arg_mode' => 'fast',
                'arg_limit' => null,
            ];

            $args = JobFormBuilder::resolveArguments(ScalarArgJob::class, $data);
            $job = new ScalarArgJob(...$args);

            expect($job->recipient)->toBe('test@example.com')
                ->and($job->note)->toBeNull()
                ->and($job->retries)->toBe(5)
                ->and($job->threshold)->toBe(0.9)
                ->and($job->notify)->toBeTrue()
                ->and($job->mode)->toBe('fast')
                ->and($job->limit)->toBeNull();
        });
    });
});
