<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum AdtEventType: string implements HasColor, HasLabel
{
    case Admitted = 'admitted';
    case TransferredInternal = 'transferred_internal';
    case TransferredIn = 'transferred_in';
    case TransferredOut = 'transferred_out';
    case Discharged = 'discharged';
    case BedAssigned = 'bed_assigned';
    case Cancelled = 'cancelled';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Admitted => 'Admitted',
            self::TransferredInternal => 'Internal transfer',
            self::TransferredIn => 'Transfer in',
            self::TransferredOut => 'Transfer out',
            self::Discharged => 'Discharged',
            self::BedAssigned => 'Bed assigned',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Admitted, self::TransferredIn => 'success',
            self::TransferredInternal, self::BedAssigned => 'info',
            self::TransferredOut => 'warning',
            self::Discharged => 'gray',
            self::Cancelled => 'danger',
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
