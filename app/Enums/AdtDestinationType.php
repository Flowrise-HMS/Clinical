<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum AdtDestinationType: string implements HasLabel
{
    case InternalUnit = 'internal_unit';
    case Branch = 'branch';
    case ExternalFacility = 'external_facility';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::InternalUnit => 'Internal unit',
            self::Branch => 'Another branch',
            self::ExternalFacility => 'External facility',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
