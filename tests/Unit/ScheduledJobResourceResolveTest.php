<?php

use CodeTechNL\TaskBridgeFilament\Resources\ScheduledJobResource;
use CodeTechNL\TaskBridgeFilament\Tests\Fixtures\AttributeLabelJob;
use CodeTechNL\TaskBridgeFilament\Tests\Fixtures\AttributeOverridesInterfaceJob;
use CodeTechNL\TaskBridgeFilament\Tests\Fixtures\MarkerAttributeWithInterfaceJob;
use CodeTechNL\TaskBridgeFilament\Tests\Fixtures\NoArgJob;

describe('ScheduledJobResource::resolveLabel()', function () {
    it('returns the attribute name when set', function () {
        expect(ScheduledJobResource::resolveLabel(AttributeLabelJob::class))
            ->toBe('My Attribute Label');
    });

    it('returns attribute name even when HasCustomLabel interface is also implemented', function () {
        expect(ScheduledJobResource::resolveLabel(AttributeOverridesInterfaceJob::class))
            ->toBe('Attribute Name Wins');
    });

    it('falls back to the interface taskLabel() when attribute name is null', function () {
        expect(ScheduledJobResource::resolveLabel(MarkerAttributeWithInterfaceJob::class))
            ->toBe('Interface Label (should be used)');
    });

    it('falls back to a sentence-cased class name when neither attribute nor interface is set', function () {
        expect(ScheduledJobResource::resolveLabel(NoArgJob::class))
            ->toBe('No arg job');
    });

    it('returns the class basename with a warning symbol for non-existent classes', function () {
        expect(ScheduledJobResource::resolveLabel('NonExistent\\Job'))
            ->toBe('Job ⚠');
    });
});

describe('ScheduledJobResource::resolveGroup()', function () {
    it('returns the attribute group when set', function () {
        expect(ScheduledJobResource::resolveGroup(AttributeLabelJob::class))
            ->toBe('My Attribute Group');
    });

    it('returns attribute group even when HasGroup interface is also implemented', function () {
        expect(ScheduledJobResource::resolveGroup(AttributeOverridesInterfaceJob::class))
            ->toBe('Attribute Group Wins');
    });

    it('falls back to the interface group() when attribute group is null', function () {
        expect(ScheduledJobResource::resolveGroup(MarkerAttributeWithInterfaceJob::class))
            ->toBe('Interface Group (should be used)');
    });

    it('derives group from the parent namespace segment when no attribute or interface is set', function () {
        // NoArgJob lives under Tests\Fixtures — 'Fixtures' is not in the root segments
        // list, so the namespace-based fallback returns it as the group.
        expect(ScheduledJobResource::resolveGroup(NoArgJob::class))
            ->toBe('Fixtures');
    });

    it('returns null for a non-existent class', function () {
        expect(ScheduledJobResource::resolveGroup('NonExistent\\Job'))
            ->toBeNull();
    });
});
