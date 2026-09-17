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
    case AdmissionRequested = 'admission_requested';
    case AdmissionRejected = 'admission_rejected';

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
            self::AdmissionRequested => 'Admission requested',
            self::AdmissionRejected => 'Admission rejected',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Admitted, self::TransferredIn => 'success',
            self::TransferredInternal, self::BedAssigned => 'info',
            self::TransferredOut => 'warning',
            self::Discharged => 'gray',
            self::Cancelled, self::AdmissionRejected => 'danger',
            self::AdmissionRequested => 'warning',
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
