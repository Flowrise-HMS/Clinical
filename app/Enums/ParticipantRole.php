<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum ParticipantRole: string implements HasColor, HasDescription, HasLabel
{
    case PRIMARY_PROVIDER = 'primary_provider';
    case ATTENDING = 'attending';
    case CONSULTANT = 'consultant';
    case NURSE = 'nurse';
    case RESIDENT = 'resident';
    case INTERN = 'intern';
    case TECHNICIAN = 'technician';
    case PHARMACIST = 'pharmacist';
    case SOCIAL_WORKER = 'social_worker';
    case OTHER = 'other';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::PRIMARY_PROVIDER => 'Primary Provider',
            self::ATTENDING => 'Attending Physician',
            self::CONSULTANT => 'Consultant',
            self::NURSE => 'Nurse',
            self::RESIDENT => 'Resident',
            self::INTERN => 'Intern',
            self::TECHNICIAN => 'Technician',
            self::PHARMACIST => 'Pharmacist',
            self::SOCIAL_WORKER => 'Social Worker',
            self::OTHER => 'Other',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::PRIMARY_PROVIDER => 'Main physician responsible for patient care',
            self::ATTENDING => 'Senior physician overseeing the case',
            self::CONSULTANT => 'Specialist providing consultation',
            self::NURSE => 'Registered nurse providing direct care',
            self::RESIDENT => 'Medical resident in training',
            self::INTERN => 'Medical intern in training',
            self::TECHNICIAN => 'Technical staff (lab, radiology, etc.)',
            self::PHARMACIST => 'Pharmacy staff',
            self::SOCIAL_WORKER => 'Medical social worker',
            self::OTHER => 'Other healthcare professional',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PRIMARY_PROVIDER => 'primary',
            self::ATTENDING => 'info',
            self::CONSULTANT => 'warning',
            self::NURSE => 'success',
            self::RESIDENT => 'secondary',
            self::INTERN => 'gray',
            self::TECHNICIAN => 'primary',
            self::PHARMACIST => 'warning',
            self::SOCIAL_WORKER => 'secondary',
            self::OTHER => 'gray',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isPhysician(): bool
    {
        return in_array($this, [self::PRIMARY_PROVIDER, self::ATTENDING, self::CONSULTANT, self::RESIDENT]);
    }

    public function isNurse(): bool
    {
        return $this === self::NURSE;
    }

    public function isTrainee(): bool
    {
        return in_array($this, [self::RESIDENT, self::INTERN]);
    }
}
