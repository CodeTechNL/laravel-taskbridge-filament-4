<?php

namespace CodeTechNL\TaskBridgeFilament\Enums;

enum JobPickerSize: string
{
    case Medium = 'medium';
    case Large = 'large';
    case Xl = 'xl';

    public function maxWidth(): string
    {
        return match ($this) {
            self::Medium => '48rem',
            self::Large => '72rem',
            self::Xl => '90rem',
        };
    }

    public function columns(): int
    {
        return match ($this) {
            self::Medium => 2,
            self::Large => 3,
            self::Xl => 4,
        };
    }
}
