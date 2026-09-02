<?php

namespace Modules\Clinical\Filament\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Modules\Core\Enums\CoverageType;

/**
 * The coverage + NHIS claim-code pair shared by every encounter-creation
 * surface (EncounterResource form, Clinical Workspace, patient quick actions,
 * relation managers). Single source of truth so the surfaces cannot drift.
 */
class EncounterCoverageSchema
{
    public static function coverageField(): Select
    {
        return Select::make('coverage_type')
            ->label('Coverage Type')
            ->options(CoverageType::class)
            ->native(false)
            ->required()
            ->live();
    }

    public static function claimCheckCodeField(): TextInput
    {
        return TextInput::make('claim_check_code')
            ->label('NHIS Claim Check Code')
            ->rules(['nullable', 'regex:/^([A-Za-z0-9]{5}|[A-Za-z0-9]{13})$/'])
            ->helperText('Leave blank to auto-generate from NHIA (OTAC) when the encounter is saved. To enter manually instead, dial *842# from an authorized facility phone and use option 1 ("Generate Claim Code").')
            ->visible(function (Get $get): bool {
                $coverage = $get('coverage_type');

                return $coverage === CoverageType::NHIS || $coverage === CoverageType::NHIS->value;
            })
            ->columnSpanFull();
    }

    /**
     * @return array{0: Select, 1: TextInput}
     */
    public static function fields(): array
    {
        return [self::coverageField(), self::claimCheckCodeField()];
    }
}
