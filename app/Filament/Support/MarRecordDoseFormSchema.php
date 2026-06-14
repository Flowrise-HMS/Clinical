<?php

namespace Modules\Clinical\Filament\Support;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\HtmlString;
use Modules\Clinical\Classes\Services\MedicationAdministrationService;
use Modules\Clinical\Classes\Services\MedicationFulfillmentPolicy;
use Modules\Clinical\Enums\MedicationAdministrationStatus;
use Modules\Clinical\Models\RequestItem;
use Modules\Pharmacy\Models\Medication;

class MarRecordDoseFormSchema
{
    /**
     * @return array<int, mixed>
     */
    public static function forSingleItem(RequestItem $item, bool $compact = false): array
    {
        $detail = $item->prescriptionDetail;
        $policy = app(MedicationFulfillmentPolicy::class);
        $marService = app(MedicationAdministrationService::class);
        $allergies = $marService->getPatientAllergiesForMar($item);
        $isControlled = $policy->isControlledMedication($item);
        $remaining = $marService->getRemainingDoses($item);

        $fields = [];

        if ($allergies->isNotEmpty()) {
            $allergyText = $allergies->map(fn ($a) => $a->display_label)->implode(', ');
            $fields[] = Placeholder::make('allergy_banner')
                ->label('Allergies')
                ->content(new HtmlString('<div class="text-danger-600 dark:text-danger-400 font-medium">⚠ '.$allergyText.'</div>'));
        }

        if ($policy->requiresPaymentBeforeMarOrDispense($item) && ! $policy->isPaidFor($item)) {
            $fields[] = Placeholder::make('payment_required')
                ->content('Payment is required before recording this dose.');
        }

        $fields[] = Hidden::make('request_item_id')->default($item->id);

        if (! $compact) {
            $fields[] = Placeholder::make('medication_info')
                ->label('Medication')
                ->content($item->service?->name.' — '.($detail?->frequency ?? '').' ['.$remaining.' remaining]');
        }

        $fields[] = Select::make('status')
            ->label('Status')
            ->options(MedicationAdministrationStatus::class)
            ->default(MedicationAdministrationStatus::GIVEN)
            ->required()
            ->live();

        $fields[] = DateTimePicker::make('started_at')
            ->label('Administered at')
            ->default(now())
            ->required();

        $fields[] = DateTimePicker::make('ended_at')
            ->label('Ended at')
            ->default(now());

        $fields[] = TextInput::make('quantity_given')
            ->label('Dose quantity')
            ->numeric()
            ->default(1)
            ->minValue(1)
            ->visible(fn ($get): bool => ($get('status') ?? 'given') === MedicationAdministrationStatus::GIVEN->value);

        $fields[] = Select::make('dose_unit_id')
            ->label('Dose unit')
            ->options(fn () => \Modules\Core\Models\Unit::pluck('label', 'id'))
            ->default($detail?->dose_unit_id)
            ->searchable();

        $fields[] = Textarea::make('prn_reason')
            ->label('PRN reason / indication')
            ->rows(2)
            ->required(fn (): bool => (bool) $detail?->prn)
            ->visible(fn (): bool => (bool) $detail?->prn);

        $fields[] = Textarea::make('omission_reason')
            ->label('Omission / refusal reason')
            ->rows(2)
            ->required(fn ($get): bool => in_array($get('status'), [
                MedicationAdministrationStatus::OMITTED->value,
                MedicationAdministrationStatus::REFUSED->value,
            ], true))
            ->visible(fn ($get): bool => in_array($get('status'), [
                MedicationAdministrationStatus::OMITTED->value,
                MedicationAdministrationStatus::REFUSED->value,
            ], true));

        if ($isControlled) {
            $fields[] = Toggle::make('witness_confirmed')
                ->label('Witness present — I confirm a second licensed staff member witnessed this administration')
                ->required()
                ->visible(fn ($get): bool => ($get('status') ?? 'given') === MedicationAdministrationStatus::GIVEN->value);
        }

        return $fields;
    }

    /**
     * @return array<int, mixed>
     */
    public static function dispenseFields(RequestItem $item): array
    {
        $medications = Medication::query()
            ->where('service_id', $item->service_id)
            ->where('is_active', true)
            ->pluck('generic_name', 'id');

        return [
            Select::make('medication_id')
                ->label('Medication')
                ->options($medications)
                ->required()
                ->searchable(),
            TextInput::make('quantity')
                ->label('Quantity to dispense')
                ->numeric()
                ->default($item->quantity)
                ->minValue(1)
                ->required(),
            TextInput::make('batch_number')
                ->label('Batch number'),
            TextInput::make('expiry_date')
                ->label('Expiry date')
                ->type('date'),
            Textarea::make('notes')
                ->label('Notes')
                ->rows(2),
        ];
    }
}
