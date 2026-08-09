<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Modules\Clinical\Filament\Clusters\Clinical\ClinicalCluster;
use Modules\Clinical\Settings\ClinicalSettings;
use Modules\Core\Enums\NavigationGroup;

class ManageClinicalSettings extends SettingsPage
{
    use HasPageShield;

    protected static ?string $cluster = ClinicalCluster::class;

    protected static string $settings = ClinicalSettings::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::SETTINGS;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Clinical';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Medication administration (MAR)'))
                    ->columns(2)
                    ->schema([
                        Toggle::make('mar_require_payment_before')
                            ->label(__('Require payment before MAR')),
                        Toggle::make('mar_emergency_exempt')
                            ->label(__('Emergency orders exempt from payment gate')),
                        Toggle::make('mar_allergy_block_on_match')
                            ->label(__('Block MAR when allergy matches')),
                        Toggle::make('mar_reminders_enabled')
                            ->label(__('Enable MAR dose reminders')),
                        TextInput::make('mar_reminders_lead_minutes')
                            ->label(__('Reminder lead time (minutes)'))
                            ->numeric()
                            ->minValue(1),
                        TextInput::make('mar_schedule_grace_minutes')
                            ->label(__('Dose grace window (minutes)'))
                            ->numeric()
                            ->minValue(0),
                        CheckboxList::make('mar_reminders_channels')
                            ->label(__('Reminder channels'))
                            ->options([
                                'database' => __('In-app'),
                                'mail' => __('Email'),
                            ])
                            ->columnSpanFull(),
                    ]),
                Section::make(__('Encounter defaults'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('default_encounter_type')
                            ->label(__('Default encounter type')),
                        TextInput::make('default_encounter_class')
                            ->label(__('Default encounter class')),
                        TextInput::make('default_encounter_status')
                            ->label(__('Default encounter status')),
                        Toggle::make('default_requires_payment_before')
                            ->label(__('Default requires payment before (new services)')),
                        Toggle::make('controlled_substances_witness_required')
                            ->label(__('Witness required for controlled substances on MAR')),
                    ]),
            ]);
    }
}
