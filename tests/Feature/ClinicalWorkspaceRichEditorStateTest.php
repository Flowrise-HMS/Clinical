<?php

use Modules\Clinical\Enums\AdtDestinationType;
use Modules\Clinical\Enums\DischargeDisposition;
use Modules\Clinical\Enums\NoteStatus;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @return array<string, mixed>
 */
function expectedAdtFormDefaults(): array
{
    return [
        'ward_id' => null,
        'bed_id' => null,
        'notes' => null,
        'transfer_ward_id' => null,
        'transfer_bed_id' => null,
        'transfer_notes' => null,
        'destination_type' => AdtDestinationType::ExternalFacility->value,
        'destination_branch_id' => null,
        'destination_label' => null,
        'transfer_out_notes' => null,
        'admission_source' => 'local',
        'source_label' => null,
        'from_branch_id' => null,
        'transfer_in_chief_complaint' => null,
        'transfer_in_ward_id' => null,
        'transfer_in_bed_id' => null,
        'transfer_in_notes' => null,
    ];
}

it('defines nested RichEditor state keys for Livewire entangle', function (): void {
    $defaults = (new ReflectionClass(ClinicalWorkspace::class))->getDefaultProperties();

    expect($defaults['consultationData'])->toBe(['notes' => null])
        ->and($defaults['referralData'])->toBe(['notes' => null])
        ->and($defaults['noteFormData'])->toBe(['content' => null, 'status' => NoteStatus::DRAFT->value])
        ->and($defaults['dischargeData'])->toBe([
            'discharge_notes' => null,
            'discharge_disposition' => DischargeDisposition::COMPLETED->value,
        ])
        ->and($defaults['adtFormData'])->toBe(expectedAdtFormDefaults());
});

it('restores nested RichEditor state keys when form states are reset', function (): void {
    $page = (new ReflectionClass(ClinicalWorkspace::class))->newInstanceWithoutConstructor();
    $page->consultationData = ['notes' => '<p>temp</p>'];
    $page->referralData = [];
    $page->noteFormData = [];
    $page->dischargeData = [];
    $page->adtFormData = [];
    $page->encounterFormData = ['foo' => 'bar'];
    $page->consultationChiefComplaint = 'pain';
    $page->consultationNotes = 'old';
    $page->diagnosisFormData = ['x' => 1];
    $page->vitalsData = ['y' => 1];
    $page->serviceRequestData = ['z' => 1];
    $page->labResultData = ['a' => 1];
    $page->allergyData = ['b' => 1];
    $page->medicationData = ['c' => 1];

    $method = new ReflectionMethod(ClinicalWorkspace::class, 'resetFormStates');
    $method->invoke($page);

    expect($page->consultationData)->toBe(['notes' => null])
        ->and($page->referralData)->toBe(['notes' => null])
        ->and($page->noteFormData)->toBe(['content' => null, 'status' => NoteStatus::DRAFT->value])
        ->and($page->dischargeData)->toBe([
            'discharge_notes' => null,
            'discharge_disposition' => DischargeDisposition::COMPLETED->value,
        ])
        ->and($page->adtFormData)->toBe(expectedAdtFormDefaults())
        ->and($page->consultationChiefComplaint)->toBe('')
        ->and($page->encounterFormData)->toBe([]);
});
